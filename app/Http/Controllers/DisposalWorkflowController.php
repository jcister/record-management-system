<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteDisposalRequest;
use App\Http\Requests\ScheduleDisposalRequest;
use App\Models\DisposalBatch;
use App\Models\DisposalBatchFile;
use App\Models\File;
use App\Support\DateTimeFormatter;
use App\Support\DisposalReportWriter;
use App\Support\RetentionSchedule;
use App\Support\RetentionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisposalWorkflowController extends Controller
{
    public function __construct(private readonly DisposalReportWriter $reportWriter) {}

    public function schedule(ScheduleDisposalRequest $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($request, $user): void {
            $files = $this->fetchFiles($request->fileIds(), [RetentionStatus::ACTIVE, RetentionStatus::READY_FOR_DISPOSAL]);

            $scheduledAt = $request->scheduledAt()
                ? DateTimeFormatter::parse($request->scheduledAt())
                : DateTimeFormatter::now();

            $batch = $this->createBatch(
                $user->id,
                DisposalBatch::STATUS_SCHEDULED,
                $files->count(),
                $request->note(),
                $scheduledAt,
                $request->modeOfDisposal()
            );

            $reportPath = $this->reportWriter->write($batch, $files, 'scheduled');

            $batch->forceFill([
                'nap_report_path' => $reportPath,
                'nap_report_stage' => 'scheduled',
                'nap_report_generated_by' => $user->id,
                'nap_report_generated_at' => now(),
            ])->save();

            foreach ($files as $file) {
                $this->attachFile($batch, $file, $user->id, DisposalBatch::STATUS_SCHEDULED, $scheduledAt);
                // Files retain their current status (ACTIVE or READY_FOR_DISPOSAL) until disposal is manually completed
            }

            $this->recordAudit($batch, $user->id, 'scheduled', $files);
        });

        return to_route('disposal-management');
    }

    public function complete(CompleteDisposalRequest $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($request, $user): void {
            $files = $this->fetchFiles(
                $request->fileIds(),
                [RetentionStatus::ACTIVE, RetentionStatus::READY_FOR_DISPOSAL]
            );

            $completedAt = $request->completedAt()
                ? DateTimeFormatter::parse($request->completedAt())
                : DateTimeFormatter::now();

            $batch = $this->createBatch($user->id, DisposalBatch::STATUS_COMPLETED, $files->count(), null, $completedAt, null);

            $reportPath = $this->reportWriter->write($batch, $files, 'completed');

            $batch->forceFill([
                'nap_report_path' => $reportPath,
                'nap_report_stage' => 'completed',
                'nap_report_generated_by' => $user->id,
                'nap_report_generated_at' => now(),
                'completed_by' => $user->id,
                'completed_at' => $completedAt,
            ])->save();

            // Record audit BEFORE deleting files (to avoid foreign key constraint violation)
            $this->recordAudit($batch, $user->id, 'completed', $files);

            foreach ($files as $file) {
                $this->attachFile($batch, $file, $user->id, DisposalBatch::STATUS_COMPLETED, $completedAt);
            }
        });

        return to_route('disposal-management');
    }

    /**
     * @param  list<string>  $fileIds
     * @param  list<string>  $allowedStatuses
     * @return Collection<int, File>
     */
    private function fetchFiles(array $fileIds, array $allowedStatuses): Collection
    {
        $files = File::query()
            ->with(['physicalLocationTags:id,name', 'versions:id,file_id,file_path'])
            ->whereIn('id', $fileIds)
            ->where('is_record', true)
            ->lockForUpdate()
            ->get();

        if ($files->count() !== count($fileIds)) {
            throw ValidationException::withMessages([
                'file_ids' => 'One or more selected records are missing.',
            ]);
        }

        $invalid = $files->reject(fn (File $file) => in_array($file->retention_status, $allowedStatuses, true));

        if ($invalid->isNotEmpty()) {
            throw ValidationException::withMessages([
                'file_ids' => 'All selected records must be ready for this action.',
            ]);
        }

        $restricted = $files->filter(fn (File $file) => $file->retention_duration === RetentionSchedule::DURATION_PERPETUAL);

        if ($restricted->isNotEmpty()) {
            throw ValidationException::withMessages([
                'file_ids' => 'Perpetual-retention records cannot be disposed.',
            ]);
        }

        return $files;
    }

    private function createBatch(string $userId, string $status, int $fileCount, ?string $note = null, $timestamp = null, ?string $modeOfDisposal = null): DisposalBatch
    {
        $sequence = $this->nextSequence();
        $timestamp = $timestamp ?? now();

        return DisposalBatch::query()->create([
            'sequence' => $sequence,
            'reference' => sprintf('DB-%s-%04d', now()->format('Ymd'), $sequence),
            'status' => $status,
            'file_count' => $fileCount,
            'scheduled_by' => $userId,
            'scheduled_at' => $timestamp,
            'scheduled_note' => $note,
            'mode_of_disposal' => $modeOfDisposal,
            'metadata' => [
                'source' => 'disposal-management',
            ],
        ]);
    }

    private function nextSequence(): int
    {
        $latest = DisposalBatch::query()
            ->select('sequence')
            ->orderByDesc('sequence')
            ->lockForUpdate()
            ->value('sequence');

        return ((int) $latest) + 1;
    }

    private function attachFile(DisposalBatch $batch, File $file, string $userId, string $status, $timestamp = null): DisposalBatchFile
    {
        $timestamp = $timestamp ?? now();

        $attributes = [
            'file_id' => $file->id,
            'status' => $status,
            'scheduled_by' => $userId,
            'scheduled_at' => $timestamp,
            'physical_snapshot' => $this->physicalSnapshot($file),
        ];

        if ($status === DisposalBatch::STATUS_COMPLETED) {
            $attributes['completed_by'] = $userId;
            $attributes['completed_at'] = $timestamp;
        }

        return $batch->items()->create($attributes);
    }

    private function physicalSnapshot(File $file): array
    {
        return [
            'location' => $file->physical_location,
            'tags' => $file->physicalLocationTags
                ->map(fn ($tag) => [
                    'id' => (int) $tag->id,
                    'name' => $tag->name,
                ])
                ->values()
                ->all(),
        ];
    }

    private function recordAudit(DisposalBatch $batch, string $userId, string $action, Collection $files): void
    {
        $batch->audits()->createMany(
            $files->map(fn (File $file) => [
                'file_id' => $file->id,
                'user_id' => $userId,
                'action' => $action,
                'description' => sprintf('File %s marked %s.', $file->record_id ?? $file->id, $action),
                'meta' => [
                    'batch_reference' => $batch->reference,
                    'retention_status' => $file->retention_status,
                ],
            ])->all()
        );
    }
}
