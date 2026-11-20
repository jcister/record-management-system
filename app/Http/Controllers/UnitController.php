<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    public function index(): JsonResponse
    {
        $units = Unit::query()
            ->withTrashed()
            ->orderBy('name')
            ->get(['id', 'name', 'deleted_at']);

        return response()->json(['data' => $units]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('units', 'name')],
        ]);

        $unit = Unit::create($data);

        return response()->json(['data' => $unit], 201);
    }

    public function update(Request $request, Unit $unit): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('units', 'name')->ignore($unit->id)],
        ]);

        $unit->update($data);

        return response()->json(['data' => $unit]);
    }

    public function destroy(Unit $unit): JsonResponse
    {
        $unit->delete();

        return response()->json(['message' => 'deleted'], 200);
    }

    public function restore(int $id): JsonResponse
    {
        $unit = Unit::withTrashed()->findOrFail($id);
        $unit->restore();

        return response()->json(['data' => $unit], 200);
    }
}
