<?php

use App\Http\Controllers\SearchController;
use App\Models\Bookmark;
use App\Models\Department;
use App\Models\File;
use App\Models\Folder;
use App\Services\SearchExperienceService;
use App\Support\Utf8Sanitizer;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

$applyBookmarks = static function (Collection $models, string $bookmarkableType, int|string $userId): Collection {
    if ($models->isEmpty()) {
        return $models;
    }

    $bookmarksByBookmarkableId = Bookmark::query()
        ->where('user_id', $userId)
        ->where('bookmarkable_type', $bookmarkableType)
        ->whereIn('bookmarkable_id', $models->pluck('id'))
        ->get()
        ->keyBy('bookmarkable_id');

    return $models->map(function ($model) use ($bookmarksByBookmarkableId) {
        $bookmark = $bookmarksByBookmarkableId->get($model->id);

        $model->is_bookmarked = $bookmark !== null;
        $model->bookmark_id = $bookmark?->id;

        return $model;
    });
};

Route::get('search', SearchController::class)
    ->middleware(['auth', 'verified', 'can:organize-content'])
    ->name('search');

Route::middleware(['auth', 'verified', 'can:delete-content'])
    ->prefix('records')
    ->name('records.')
    ->group(function (): void {

        Route::get('/trash', function () {
            $folders = Folder::onlyTrashed()
                ->where(function ($query): void {
                    $query->whereNull('parent_id')
                        ->orWhereHas('parent', function ($parentQuery): void {
                            $parentQuery->withoutTrashed();
                        })
                        ->orWhereDoesntHave('parent', function ($parentQuery): void {
                            $parentQuery->withTrashed();
                        });
                })
                ->withCount([
                    'children as children_count' => fn ($query) => $query->onlyTrashed(),
                    'files as files_count' => fn ($query) => $query->onlyTrashed(),
                ])
                ->latest('deleted_at')
                ->get()
                ->toArray();

            // Get files that are trashed AND either have no folder or their folder is not trashed
            $files = File::onlyTrashed()
                ->with('currentVersion')
                ->with(['folder' => fn ($query) => $query->withTrashed()->select('id', 'name')])
                ->where(function ($query) {
                    $query->whereNull('folder_id')
                        ->orWhereHas('folder', function ($q) {
                            $q->withoutTrashed();
                        });
                })
                ->latest('deleted_at')
                ->get()
                ->toArray();

            $departments = Department::query()
                ->orderBy('name')
                ->get(['id', 'name']);

            $props = Utf8Sanitizer::sanitize([
                'folders' => $folders,
                'files' => $files,
                'departments' => $departments,
            ]);

            return Inertia::render('Records/Trash', $props);
        })->name('trash');

        Route::get('/trash/{folder}', function (Folder $folder) {

            // Get folder path (ancestors)
            $ancestors = [];
            $currentFolder = $folder;
            while ($currentFolder) {
                array_unshift($ancestors, [
                    'id' => $currentFolder->id,
                    'name' => $currentFolder->name,
                ]);
                $currentFolder = $currentFolder->parent_id ? Folder::withTrashed()->find($currentFolder->parent_id) : null;
            }

            $childFolders = $folder->children()
                ->withTrashed()
                ->withCount([
                    'children as children_count' => fn ($query) => $query->onlyTrashed(),
                    'files as files_count' => fn ($query) => $query->onlyTrashed(),
                ])
                ->latest('deleted_at')
                ->get()
                ->toArray();

            $files = $folder->files()
                ->withTrashed()
                ->with(['folder' => fn ($query) => $query->withTrashed()->select('id', 'name')])
                ->latest('deleted_at')
                ->get()
                ->toArray();

            $departments = Department::query()
                ->orderBy('name')
                ->get(['id', 'name']);

            $props = Utf8Sanitizer::sanitize([
                'folder' => $folder,
                'ancestors' => $ancestors,
                'folders' => $childFolders,
                'files' => $files,
                'departments' => $departments,
            ]);

            return Inertia::render('Records/Trash', $props);
        })->withTrashed()->name('trash.folder');

    });

Route::middleware(['auth', 'verified', 'can:organize-content'])
    ->prefix('records')
    ->name('records.')
    ->group(function () use ($applyBookmarks): void {
        Route::get('/', function () use ($applyBookmarks) {
            $user = Auth::user();

            $folders = $applyBookmarks(Folder::query()
                ->whereNull('parent_id')
                ->where('is_basic', true)
                ->withCount([
                    'children',
                    'files',
                ])
                ->latest()
                ->get(), Folder::class, $user->id);

            $departments = Department::select('id', 'name')->get();

            return Inertia::render('Records/AllRecords', [
                'folders' => $folders,
                // Do not show root-level files in All Records; this page is folders only.
                'files' => [],
                'departments' => $departments,
            ]);
        })->name('all-records');

        Route::get('/bookmarks', function () {
            $user = Auth::user();
            $bookmarks = Bookmark::query()
                ->where('user_id', $user->id)
                ->with([
                    'bookmarkable' => function (MorphTo $morph): void {
                        $morph->morphWith([
                            File::class => ['folder:id,name'],
                            Folder::class => [],
                        ])->morphWithCount([
                            Folder::class => [
                                'children',
                                'files',
                            ],
                        ]);
                    },
                ])
                ->latest()
                ->get()
                ->values();

            $departments = Department::query()
                ->orderBy('name')
                ->get(['id', 'name']);

            return Inertia::render('Records/Bookmarks', [
                'bookmarks' => $bookmarks,
                'departments' => $departments,
            ]);
        })->name('bookmarks');

        Route::get('/{folder}', function (Folder $folder, SearchExperienceService $experience) use ($applyBookmarks) {
            $user = Auth::user();

            $experience->recordFolderView($folder);

            // Get folder path (ancestors)
            $ancestors = [];
            $currentFolder = $folder;
            while ($currentFolder) {
                array_unshift($ancestors, [
                    'id' => $currentFolder->id,
                    'name' => $currentFolder->name,
                ]);
                $currentFolder = $currentFolder->parent_id ? Folder::find($currentFolder->parent_id) : null;
            }

            $childFolders = $applyBookmarks($folder->children()
                ->withCount([
                    'children',
                    'files',
                ])
                ->latest()
                ->get(), Folder::class, $user->id);

            $files = $applyBookmarks($folder->files()
                ->with('currentVersion')
                ->latest()
                ->get(), File::class, $user->id);

            $departments = Department::select('id', 'name')->get();

            return Inertia::render('FolderView', [
                'folder' => $folder,
                'ancestors' => $ancestors,
                'folders' => $childFolders,
                'files' => $files,
                'departments' => $departments,
            ]);
        })->whereUuid('folder')->name('show');
    });

Route::middleware(['auth', 'verified', 'can:review-content'])
    ->prefix('records')
    ->name('records.')
    ->group(function () use ($applyBookmarks): void {
        Route::get('/pending-unorganized', function () use ($applyBookmarks) {
            $user = Auth::user();

            $folders = $applyBookmarks(Folder::query()
                ->whereNull('parent_id')
                ->where('is_basic', false)
                ->withCount([
                    'children',
                    'files',
                ])
                ->latest()
                ->get(), Folder::class, $user->id);

            $departments = Department::query()
                ->orderBy('name')
                ->get(['id', 'name']);

            $regularFiles = $applyBookmarks(File::query()
                ->with('currentVersion')
                ->whereNull('folder_id')
                ->where('record_id', 'NOT LIKE', 'SCAN-%')
                ->where(function ($query) {
                    $query->where('status', '!=', 'draft')
                        ->orWhereNull('status');
                })
                ->latest()
                ->get(), File::class, $user->id);

            $scanReviewFiles = $applyBookmarks(File::query()
                ->with('currentVersion')
                ->whereNull('folder_id')
                ->where('record_id', 'LIKE', 'SCAN-%')
                ->where('status', 'pending')
                ->latest()
                ->get(), File::class, $user->id);

            $files = $regularFiles
                ->concat($scanReviewFiles)
                ->values();

            return Inertia::render('Records/Pending&Unorganized', [
                'folders' => $folders,
                'files' => $files,
                'departments' => $departments,
            ]);
        });

    });
