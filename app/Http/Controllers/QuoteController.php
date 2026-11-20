<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\JsonResponse;

class QuoteController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): JsonResponse
    {
        $quoteText = Inspiring::quote();

        // Strip console formatting tags (e.g., <options=bold>, <fg=gray>, </>)
        $quoteText = preg_replace('/<[^>]+>/', '', $quoteText);

        // Try to extract author if quote contains separator (em dash or hyphen)
        // Laravel's Inspiring quotes typically use em dash: "Quote text — Author"
        $parts = preg_split('/\s*[—–-]\s*/u', $quoteText, 2);
        $content = trim($parts[0]);
        $author = isset($parts[1]) && ! empty(trim($parts[1])) ? trim($parts[1]) : 'Laravel';

        return response()->json([
            'content' => $content,
            'author' => $author,
        ]);
    }
}
