<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\ChunkedUploadController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FileThumbnailController;
use App\Http\Controllers\FileVersionController;
use App\Http\Controllers\FileViewerController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OcrCallbackController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PositionsController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SearchFilterOptionsController;
use App\Http\Controllers\SseStreamController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public API endpoint (used on login page)
Route::get('quote', QuoteController::class)->name('api.quote');

Route::middleware(['auth:sanctum'])
    ->name('api.')
    ->group(function (): void {
        Route::get('user', function (Request $request) {
            return $request->user();
        })->name('user');

        // Read-only routes (anyone authenticated can access)
        Route::get('files/{file}/view', action: [FileViewerController::class, 'view'])->withTrashed()->name('files.view');
        Route::get('files/{file}/thumbnail', [FileThumbnailController::class, 'showCurrent'])->withTrashed()->name('files.thumbnail');
        Route::get('files/{file}/download', [FileViewerController::class, 'download'])->withTrashed()->name('files.download');
        Route::get('files/{file}/versions/{version}/download', [FileVersionController::class, 'download'])->withTrashed()->name('files.versions.download');
        Route::get('files/{file}/versions/{version}/thumbnail', [FileThumbnailController::class, 'showVersion'])->withTrashed()->name('files.versions.thumbnail');
        Route::post('downloads', [DownloadController::class, 'store'])->name('downloads.store');

        Route::apiResource('bookmarks', BookmarkController::class);
        Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::get('units', [UnitController::class, 'index'])->name('units.index');
        Route::get('positions', [PositionsController::class, 'index'])->name('positions.index');
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('tags', [TagController::class, 'index'])->name('tags.index');
        Route::get('search/filter-options', SearchFilterOptionsController::class)->name('search.filter-options');
        Route::apiResource('folders', controller: FolderController::class)
            ->only(['index', 'show'])
            ->middleware('any_ability:organize-content|delete-content|manage-requests|review-content');
    });

// SSE (Server-Sent Events) streams — require authentication only
Route::middleware(['auth:sanctum'])
    ->prefix('sse')
    ->name('api.sse.')
    ->group(function (): void {
        Route::get('files/{file}/updates', [SseStreamController::class, 'fileUpdates'])->name('file.updates');
        Route::get('records/pending-unorganized/updates', [SseStreamController::class, 'pendingUpdates'])->name('pending.updates');
        Route::get('notifications/updates', [SseStreamController::class, 'notificationUpdates'])->name('notifications.updates');
    });

// Content management routes - require upload permission (allow if any listed ability is present)
Route::middleware(['auth:sanctum', 'any_ability:upload-content|organize-content|manage-requests|delete-content'])
    ->name('api.')
    ->group(function (): void {
        Route::apiResource('files', controller: FileController::class);
        Route::apiResource('institutions', InstitutionController::class);
        Route::get('files/{file}/versions', [FileVersionController::class, 'index'])->withTrashed()->name('files.versions.index');
        Route::post('files/{file}/versions/{version}/restore', [FileVersionController::class, 'restore'])->withTrashed()->name('files.versions.restore');

        // Tag management routes
        Route::post('tags', [TagController::class, 'store'])->name('tags.store');
        Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');
        Route::post('tags/{id}/restore', [TagController::class, 'restore'])->name('tags.restore');

        // Upload token endpoint for chunked uploads
        Route::post('upload/token', [FileController::class, 'createUploadToken'])->name('upload.token');
    });

// Chunked upload routes - require upload-chunks token ability
Route::middleware(['auth:sanctum', 'abilities:upload-chunks'])
    ->prefix('files/chunked')
    ->name('api.files.chunked.')
    ->group(function (): void {
        Route::post('start', [ChunkedUploadController::class, 'start'])->name('start');
        Route::post('upload', [ChunkedUploadController::class, 'upload'])->name('upload');
        Route::post('complete', [ChunkedUploadController::class, 'complete'])->name('complete');
    });

// Content organization routes - require organize-content permission
Route::middleware(['auth:sanctum', 'can:organize-content'])
    ->name('api.')
    ->group(function (): void {
        Route::apiResource('folders', controller: FolderController::class)->only(['store', 'update']);
        Route::patch('folders/{folder}/move', [FolderController::class, 'move'])->name('folders.move');
        Route::patch('files/{file}/move', [FileController::class, 'move'])->name('files.move');
    });

// Content deletion routes - require delete-content permission
Route::middleware(['auth:sanctum', 'can:delete-content'])
    ->name('api.')
    ->group(function (): void {
        Route::delete('folders/{folder}', [FolderController::class, 'destroy'])->name('folders.destroy');
        Route::post('files/{file}/restore', [FileController::class, 'restore'])->name('files.restore');
        Route::delete('files/{file}/force', [FileController::class, 'forceDestroy'])->name('files.force');
        Route::post('folders/{folder}/restore', [FolderController::class, 'restore'])->name('folders.restore');
        Route::delete('folders/{folder}/force', [FolderController::class, 'forceDestroy'])->name('folders.force');
    });

// Document request routes
Route::middleware(['auth:sanctum'])
    ->prefix('requests')
    ->name('api.requests.')
    ->group(function (): void {
        Route::get('/', [RequestController::class, 'index'])->name('index');
        Route::get('/my', [RequestController::class, 'myRequests'])->name('my');
        Route::get('/history', [RequestController::class, 'history'])->name('history');
        Route::get('/document-types', [RequestController::class, 'getDocumentTypes'])->name('document-types');
        Route::post('/', [RequestController::class, 'store'])->name('store');
        Route::get('/{id}', [RequestController::class, 'show'])->name('show');
        Route::patch('/{id}', [RequestController::class, 'update'])->name('update');
        Route::patch('/{id}/status', [RequestController::class, 'updateStatus'])->name('status');
        Route::get('/{id}/searchable-files', [RequestController::class, 'searchableFiles'])->name('searchable-files');
        Route::post('/{id}/attach-file', [RequestController::class, 'attachFile'])->name('attach-file');
        Route::delete('/{id}/detach-file/{fileId}', [RequestController::class, 'detachFile'])->name('detach-file');
        Route::post('/{id}/upload-scan', [RequestController::class, 'uploadScan'])->name('upload-scan');
        Route::post('/{id}/submit-scans', [RequestController::class, 'submitScansForReview'])->name('submit-scans');
        Route::delete('/{id}/scans/{fileId}', [RequestController::class, 'destroyDraftScan'])->name('delete-scan');
        Route::post('/{id}/scans/{fileId}/approve', [RequestController::class, 'approveScan'])
            ->middleware('can:review-content')
            ->name('approve-scan');
        Route::post('/{id}/scans/{fileId}/reject', [RequestController::class, 'rejectScan'])
            ->middleware('can:review-content')
            ->name('reject-scan');
    });

// Admin routes - require manage-users permission
Route::middleware(['auth:sanctum', 'can:manage-users'])
    ->name('api.')
    ->group(function (): void {
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('permissions', PermissionController::class);
        // CRUD for basic lookup resources
        Route::apiResource('departments', DepartmentController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('units', UnitController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('positions', PositionsController::class)->only(['store', 'update', 'destroy']);

        // Restore soft-deleted items
        Route::post('departments/{id}/restore', [DepartmentController::class, 'restore'])->name('departments.restore');
        Route::post('units/{id}/restore', [UnitController::class, 'restore'])->name('units.restore');
        Route::post('positions/{id}/restore', [PositionsController::class, 'restore'])->name('positions.restore');
    });

// Notification routes
Route::middleware(['auth:sanctum'])
    ->prefix('notifications')
    ->name('api.notifications.')
    ->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
    });

// Audit logs - require manage-log permission
Route::middleware(['auth:sanctum', 'can:manage-log'])
    ->name('api.')
    ->group(function (): void {
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

// OCR webhook — no Sanctum auth; authenticated via HMAC-SHA256 signature
// (X-OCR-Signature header). Called by the external OCR microservice or the
// standalone OCR worker when processing completes.
Route::post('webhooks/ocr/callback', OcrCallbackController::class)->name('api.webhooks.ocr.callback');
