<?php

namespace App\Http\Controllers;

use App\Http\Requests\InstitutionRequest;
use App\Models\Institution;
use Illuminate\Http\JsonResponse;

class InstitutionController extends Controller
{
    public function index(): JsonResponse
    {
        $institutions = Institution::query()
            ->withCount('files')
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json($institutions);
    }

    public function store(InstitutionRequest $request): JsonResponse
    {
        $institution = Institution::query()->create($request->validated());

        return response()->json($institution->loadCount('files'), 201);
    }

    public function show(Institution $institution): JsonResponse
    {
        return response()->json($institution->load(['files' => fn ($query) => $query->latest()]));
    }

    public function update(InstitutionRequest $request, Institution $institution): JsonResponse
    {
        $institution->fill($request->validated())->save();

        return response()->json($institution->refresh()->loadCount('files'));
    }

    public function destroy(Institution $institution): JsonResponse
    {
        $institution->delete();

        return response()->json(null, 204);
    }
}
