<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookmarkRequest;
use App\Models\Bookmark;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $bookmarks = Bookmark::query()
            ->where('user_id', $request->user()->id)
            ->with('bookmarkable')
            ->latest()
            ->paginate($perPage);

        return response()->json($bookmarks);
    }

    public function store(BookmarkRequest $request): JsonResponse
    {
        $attributes = $request->validated();
        $attributes['user_id'] = $request->user()->id;

        $this->assertBookmarkableExists($attributes['bookmarkable_type'], $attributes['bookmarkable_id']);

        // Check if bookmark already exists (including soft deleted)
        $existing = Bookmark::withTrashed()
            ->where('user_id', $attributes['user_id'])
            ->where('bookmarkable_id', $attributes['bookmarkable_id'])
            ->where('bookmarkable_type', $attributes['bookmarkable_type'])
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();

                return response()->json($existing->refresh()->load('bookmarkable'), 200);
            }

            return response()->json(['message' => 'Item already bookmarked'], 409);
        }

        $bookmark = Bookmark::query()->create($attributes);

        return response()->json($bookmark->load('bookmarkable'), 201);
    }

    public function show(Request $request, Bookmark $bookmark): JsonResponse
    {
        $this->authorizeBookmark($bookmark, $request);

        return response()->json($bookmark->load('bookmarkable'));
    }

    public function update(BookmarkRequest $request, Bookmark $bookmark): JsonResponse
    {
        $this->authorizeBookmark($bookmark, $request);

        $attributes = $request->validated();
        $this->assertBookmarkableExists($attributes['bookmarkable_type'], $attributes['bookmarkable_id']);

        $bookmark->fill($attributes)->save();

        return response()->json($bookmark->refresh()->load('bookmarkable'));
    }

    public function destroy(Request $request, Bookmark $bookmark): JsonResponse
    {
        $this->authorizeBookmark($bookmark, $request);

        $bookmark->delete();

        return response()->json(null, 204);
    }

    protected function authorizeBookmark(Bookmark $bookmark, Request $request): void
    {
        abort_unless($bookmark->user_id === $request->user()->id, 403);
    }

    protected function assertBookmarkableExists(string $type, string $id): void
    {
        abort_unless(in_array($type, [Folder::class, File::class], true), 422, 'Invalid bookmarkable type.');

        $type::query()->findOrFail($id);
    }
}
