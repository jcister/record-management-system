<?php

namespace App\Http\Controllers;

use App\Services\SearchSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchSuggestionController extends Controller
{
    public function __construct(
        private readonly SearchSuggestionService $searchSuggestionService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $search = (string) $request->query('search', '');
        $limit = (int) $request->integer('limit', 5);
        $limit = max(1, min($limit, 10));

        $results = $this->searchSuggestionService->getSuggestions($search, $limit);

        return response()->json($results);
    }
}
