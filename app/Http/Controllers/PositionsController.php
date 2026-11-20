<?php

namespace App\Http\Controllers;

use App\Models\Positions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PositionsController extends Controller
{
    public function index(): JsonResponse
    {
        $positions = Positions::query()
            ->withTrashed()
            ->orderBy('name')
            ->get(['id', 'name', 'deleted_at']);

        return response()->json(['data' => $positions]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('positions', 'name')],
        ]);

        $pos = Positions::create($data);

        return response()->json(['data' => $pos], 201);
    }

    public function update(Request $request, Positions $position): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('positions', 'name')->ignore($position->id)],
        ]);

        $position->update($data);

        return response()->json(['data' => $position]);
    }

    public function destroy(Positions $position): JsonResponse
    {
        $position->delete();

        return response()->json(['message' => 'deleted'], 200);
    }

    public function restore(int $id): JsonResponse
    {
        $position = Positions::withTrashed()->findOrFail($id);
        $position->restore();

        return response()->json(['data' => $position], 200);
    }
}
