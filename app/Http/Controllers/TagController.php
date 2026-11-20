<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    /**
     * List all tags, optionally filtered by type. Pass ?with_trashed=1 to include soft-deleted tags.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tag::query()->orderBy('name');

        if ($request->has('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        $tags = $query->get();

        return response()->json($tags);
    }

    /**
     * Create a new tag. If a soft-deleted tag with the same name and type exists, it is restored.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['metadata', 'physical_location'])],
        ]);

        $existing = Tag::withTrashed()
            ->where('name', $validated['name'])
            ->where('type', $validated['type'])
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return response()->json($existing, 200);
        }

        $tag = Tag::create($validated);

        return response()->json($tag, 201);
    }

    /**
     * Soft-delete a tag. Files that use this tag are unaffected.
     */
    public function destroy(Tag $tag): JsonResponse
    {
        $tag->delete();

        return response()->json(null, 204);
    }

    /**
     * Restore a soft-deleted tag.
     */
    public function restore(int $id): JsonResponse
    {
        $tag = Tag::withTrashed()->findOrFail($id);
        $tag->restore();

        return response()->json($tag, 200);
    }
}
