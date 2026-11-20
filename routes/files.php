<?php

use App\Http\Controllers\FileController;
use App\Models\File;
use App\Services\SearchExperienceService;
use App\Support\RetentionStatus;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

Route::get('file/{file}/view', function (File $file, SearchExperienceService $experience) {
    $experience->recordFileView($file);

    $diskCandidates = array_values(array_unique(array_filter([
        config('filesystems.default', 'local'),
        'records',
        'local',
    ])));

    $hasPreviewFile = false;

    if (is_string($file->file_path) && $file->file_path !== '') {
        foreach ($diskCandidates as $diskName) {
            if (Storage::disk($diskName)->exists($file->file_path)) {
                $hasPreviewFile = true;
                break;
            }
        }
    }

    $previewUnavailableReason = null;

    if (! $hasPreviewFile) {
        if ($file->retention_status === RetentionStatus::DISPOSED) {
            $previewUnavailableReason = 'disposed';
        } elseif (! is_string($file->file_path) || $file->file_path === '') {
            $previewUnavailableReason = 'no_file';
        } else {
            $previewUnavailableReason = 'missing';
        }
    }

    $token = collect([
        $file->current_version_id,
        $file->latest_version_number,
        optional($file->updated_at)?->timestamp,
    ])->first(function ($value): bool {
        if ($value === null) {
            return false;
        }

        return trim((string) $value) !== '';
    });

    $urlParameters = ['file' => $file];

    if ($token !== null) {
        $urlParameters['v'] = $token;
    }

    return Inertia::render('FileView', [
        'file' => $file,
        'previewUrl' => route('api.files.view', $urlParameters),
        'downloadUrl' => route('api.files.download', $urlParameters),
        'previewAvailable' => $hasPreviewFile,
        'previewUnavailableReason' => $previewUnavailableReason,
    ]);
})->withTrashed()->middleware(['auth', 'verified', 'any_ability:organize-content|delete-content|manage-requests'])->name('file.view');

// General Action Routes
Route::get('files/{file}/download', [FileController::class, 'download'])
    ->middleware(['auth', 'verified', 'can:organize-content'])
    ->name('files.download');

Route::get('storage-locations', function () {
    return Inertia::render('StorageLocations');
})->middleware(['auth', 'verified'])->name('storage-locations');
