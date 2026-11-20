<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisposalBatch extends Model
{
    /** @use HasFactory<\Database\Factories\DisposalBatchFactory> */
    use HasFactory;

    use HasUuids;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REQUEST_LETTER_READY = 'request_letter_ready';

    public const STATUS_WAITING_FOR_INSPECTION = 'waiting_for_inspection';

    public const STATUS_INSPECTED = 'inspected';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_COMPLETE = 'complete';

    public const MODE_SALE = 'sale';

    public const MODE_LANDFILL = 'landfill_burying';

    /**
     * @return array<string, string>
     */
    public static function modeDictionary(): array
    {
        return [
            self::MODE_SALE => 'Sale',
            self::MODE_LANDFILL => 'Landfill / Burial',
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function modeOptions(): array
    {
        return collect(self::modeDictionary())
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    public static function modeLabel(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::modeDictionary()[$value] ?? null;
    }

    /**
     * Get the display label for a status value.
     */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_COMPLETED => 'Verified',
            self::STATUS_REQUEST_LETTER_READY => 'Request Letter Ready',
            self::STATUS_WAITING_FOR_INSPECTION => 'Waiting for Inspection',
            self::STATUS_INSPECTED => 'Inspected',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_COMPLETE => 'Complete',
            default => ucfirst($status),
        };
    }

    /**
     * Statuses where files remain owned by a disposal workflow and must stay out of the queue.
     *
     * @return list<string>
     */
    public static function fileReservationStatuses(): array
    {
        return [
            self::STATUS_SCHEDULED,
            self::STATUS_COMPLETED,
            self::STATUS_REQUEST_LETTER_READY,
            self::STATUS_WAITING_FOR_INSPECTION,
            self::STATUS_INSPECTED,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_COMPLETE,
        ];
    }

    /**
     * Statuses that lock batch edits for audit integrity.
     *
     * @return list<string>
     */
    public static function lockedWorkflowStatuses(): array
    {
        return [
            self::STATUS_COMPLETED,
            self::STATUS_REQUEST_LETTER_READY,
            self::STATUS_WAITING_FOR_INSPECTION,
            self::STATUS_INSPECTED,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_COMPLETE,
        ];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sequence',
        'reference',
        'status',
        'file_count',
        'scheduled_by',
        'scheduled_at',
        'scheduled_note',
        'mode_of_disposal',
        'actual_mode_of_disposal',
        'completed_by',
        'completed_at',
        'nap_report_path',
        'nap_report_stage',
        'nap_report_generated_by',
        'nap_report_generated_at',
        'nap_report_downloaded_at',
        'request_letter_path',
        'request_letter_generated_by',
        'request_letter_generated_at',
        'request_letter_downloaded_at',
        'nap_form_1_path',
        'nap_form_1_stage',
        'nap_form_1_generated_by',
        'nap_form_1_generated_at',
        'nap_form_1_downloaded_at',
        'inspected_by',
        'inspector_name',
        'inspected_at',
        'inspection_notes',
        'inspection_scheduled_at',
        'inspection_scheduled_by',
        'coa_name',
        'coa_position',
        'coa_designation',
        'approved_by',
        'approved_at',
        'approval_notes',
        'approval_documents',
        'approval_letter_path',
        'approval_letter_generated_at',
        'approval_letter_generated_by',
        'nap_authority_issued_at',
        'nap_authority_confirmed_by',
        'report_submitted_to_coa_at',
        'report_submitted_by',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'nap_report_generated_at' => 'datetime',
            'nap_report_downloaded_at' => 'datetime',
            'request_letter_generated_at' => 'datetime',
            'request_letter_downloaded_at' => 'datetime',
            'nap_form_1_generated_at' => 'datetime',
            'nap_form_1_downloaded_at' => 'datetime',
            'inspected_at' => 'datetime',
            'inspection_scheduled_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'approval_documents' => 'array',
            'approval_letter_generated_at' => 'datetime',
            'nap_authority_issued_at' => 'datetime',
            'report_submitted_to_coa_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function napAuthorityConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nap_authority_confirmed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DisposalBatchFile::class, 'batch_id');
    }

    public function files(): BelongsToMany
    {
        return $this->belongsToMany(File::class, 'disposal_batch_files', 'batch_id', 'file_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(DisposalAudit::class, 'batch_id');
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function napReportGeneratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nap_report_generated_by');
    }

    public function napForm1GeneratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nap_form_1_generated_by');
    }

    public function inspectionScheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspection_scheduled_by');
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reportSubmittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'report_submitted_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function approvalLetterGeneratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approval_letter_generated_by');
    }

    public function requestLetterGeneratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'request_letter_generated_by');
    }
}
