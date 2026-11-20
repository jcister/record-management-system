<?php

use App\Support\FileName;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\HeaderUtils;

// Temporary test route to verify filename handling
Route::get('/test-filename-encoding', function () {
    $testCases = [
        ['name' => 'CMO-NO.-4-S.-2025', 'path' => 'private/uploads/abc123.pdf', 'mime' => 'application/pdf'],
        ['name' => 'report', 'path' => 'private/uploads/report.pdf', 'mime' => 'application/pdf'],
        ['name' => 'document', 'path' => 'private/uploads/doc', 'mime' => 'application/pdf'],
    ];

    $results = [];

    foreach ($testCases as $test) {
        $filename = FileName::withPathExtension($test['name'], $test['path'], $test['mime']);
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename
        );

        $results[] = [
            'input' => $test,
            'withPathExtension' => $filename,
            'Content-Disposition' => $disposition,
        ];
    }

    return response()->json($results, 200, [], JSON_PRETTY_PRINT);
})->name('test.filename.encoding');
