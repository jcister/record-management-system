<?php

namespace App\Http\Controllers;

use App\Http\Requests\FolderRequest;
use App\Models\Bookmark;
use App\Models\Folder;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class FolderController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}
    // public function __construct()
    // {
    //     // Allow listing and viewing to any authenticated user; restrict mutations to upload-capable users.
    //     $this->middleware('can:upload-content')->except(['index', 'show']);
    // }

    public function index(Request $request): JsonResponse
    {
        $query = Folder::query()->with(['children', 'files']);

        // Filter by parent_id if provided
        if ($request->has('parent_id')) {
            $parentId = $request->input('parent_id') === 'null' ? null : $request->input('parent_id');
            $query->where('parent_id', $parentId);
        }

        // Filter by folder_id if provided (for consistency with FileController)
        if ($request->has('folder_id')) {
            $folderId = $request->input('folder_id');
            $query->where('parent_id', $folderId === 'null' ? null : $folderId);
        }

        // Search by name if provided
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query = $query->search($search);
        }

        // Allow fetching all folders without pagination (useful for move dialog, etc.)
        $perPage = $request->query('per_page');
        if ($perPage === 'all' || $perPage === '-1') {
            $folders = $query->latest()->get();
            // Wrap in a paginator-like structure for consistency
            // Use max(count, 1) to avoid division by zero when there are no folders
            $folders = new LengthAwarePaginator(
                $folders,
                $folders->count(),
                max($folders->count(), 1),
                1
            );
        } else {
            $folders = $query->latest()->paginate(is_numeric($perPage) ? (int) $perPage : 15);
        }

        $user = $request->user();

        if ($user !== null && $folders->isNotEmpty()) {
            $bookmarks = Bookmark::query()
                ->where('user_id', $user->id)
                ->whereIn('bookmarkable_id', $folders->pluck('id'))
                ->where('bookmarkable_type', Folder::class)
                ->get()
                ->keyBy('bookmarkable_id');

            $folders->getCollection()->transform(function (Folder $folder) use ($bookmarks) {
                $bookmark = $bookmarks->get($folder->id);
                $folder->setAttribute('is_bookmarked', $bookmark !== null);
                $folder->setAttribute('bookmark_id', $bookmark?->id);

                return $folder;
            });
        }

        return response()->json($folders);
    }

    public function store(FolderRequest $request): JsonResponse
    {
        $attributes = $request->validated();
        $attributes['created_by'] = $request->user()->id;

        if (! array_key_exists('is_basic', $attributes)) {
            $parentId = $attributes['parent_id'] ?? null;

            if ($parentId !== null) {
                $attributes['is_basic'] = (bool) Folder::query()
                    ->whereKey($parentId)
                    ->value('is_basic');
            } else {
                $attributes['is_basic'] = true;
            }
        }

        // Check for duplicate folder name in the same parent
        $duplicateExists = Folder::query()
            ->where('parent_id', $attributes['parent_id'] ?? null)
            ->where('name', $attributes['name'])
            ->exists();

        if ($duplicateExists) {
            $locationName = 'My CHED ONSE KMIS';
            if (isset($attributes['parent_id']) && $attributes['parent_id']) {
                $parentFolder = Folder::find($attributes['parent_id']);
                if ($parentFolder) {
                    $locationName = $parentFolder->name;
                }
            }

            return response()->json([
                'message' => "A folder named '{$attributes['name']}' already exists in {$locationName}.",
            ], 422);
        }

        $folder = Folder::query()->create($attributes);

        return response()->json($folder->load(['children', 'files']), 201);
    }

    public function show(Folder $folder): JsonResponse
    {
        return response()->json($folder->load(['children', 'files']));
    }

    public function update(FolderRequest $request, Folder $folder): JsonResponse
    {
        $folder->fill($request->validated())->save();

        return response()->json($folder->refresh()->load(['children', 'files']));
    }

    public function destroy(Request $request, Folder $folder): JsonResponse
    {
        $folder->delete();

        return response()->json(null, 204);
    }

    public function restore(string $folder, Request $request): JsonResponse
    {
        $folder = Folder::onlyTrashed()->findOrFail($folder);

        $targetParentId = $request->has('parent_id')
            ? $request->input('parent_id')
            : $folder->parent_id;

        if ($targetParentId === 'null') {
            $targetParentId = null;
        }

        if ($targetParentId !== null) {
            $parentFolder = Folder::withTrashed()->find($targetParentId);

            if ($parentFolder === null || $parentFolder->trashed()) {
                $targetParentId = null;
            }
        }

        $folder->parent_id = $targetParentId;

        $folder->restore();

        return response()->json($folder->refresh()->load(['children', 'files']));
    }

    public function forceDestroy(string $folder): JsonResponse
    {
        $folder = Folder::onlyTrashed()->findOrFail($folder);
        $folder->forceDelete();

        return response()->json(null, 204);
    }

    public function move(Folder $folder, Request $request): JsonResponse
    {
        $request->validate([
            'parent_id' => 'nullable|exists:folders,id',
            'new_name' => 'sometimes|string|max:255',
        ]);

        // Prevent moving a folder into itself or its descendants
        if ($request->parent_id) {
            $parent = Folder::find($request->parent_id);
            $current = $parent;

            while ($current) {
                if ($current->id === $folder->id) {
                    return response()->json([
                        'message' => 'Cannot move a folder into itself or its descendants.',
                    ], 422);
                }
                $current = $current->parent_id ? Folder::find($current->parent_id) : null;
            }
        }

        $folder->parent_id = $request->parent_id;

        // Handle rename if new_name is provided
        if ($request->has('new_name')) {
            $folder->name = $request->new_name;
        }

        $folder->save();

        $this->audit->log(
            'folder_moved',
            ($request->user()?->name ?? AuditService::SYSTEM_ACTOR_NAME).' moved folder "'.$folder->name.'".',
            $folder,
            [
                'destination_parent_id' => $request->parent_id,
                'new_name' => $request->new_name ?? $folder->name,
            ]
        );

        return response()->json([
            'message' => 'Folder moved successfully.',
            'data' => $folder->refresh()->load(['children', 'files']),
        ]);
    }
}
