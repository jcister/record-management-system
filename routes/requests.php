<?php

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Requests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified', 'can:manage-requests'])
    ->prefix('requests')
    ->name('requests.')
    ->group(
        function (): void {
            Route::get('/', function (Request $request) {
                $perPage = 20;

                $baseQuery = Requests::query()
                    ->with(['requestedBy:id,name,email', 'department:id,name', 'files', 'metadataTags:id,name,type'])
                    ->latest();

                if ($request->filled('highlight') && ! $request->filled('page')) {
                    $highlightValue = trim((string) $request->query('highlight'));

                    $highlightTarget = Requests::query()
                        ->select(['id', 'request_id', 'created_at'])
                        ->where('request_id', $highlightValue)
                        ->first();

                    if ($highlightTarget !== null) {
                        $precedingCount = Requests::query()
                            ->where(function ($query) use ($highlightTarget) {
                                $query->where('created_at', '>', $highlightTarget->created_at)
                                    ->orWhere(function ($inner) use ($highlightTarget) {
                                        $inner->where('created_at', $highlightTarget->created_at)
                                            ->where('id', '>', $highlightTarget->id);
                                    });
                            })
                            ->count();

                        $highlightPage = (int) floor($precedingCount / $perPage) + 1;

                        if ($highlightPage > 1) {
                            return redirect()->route('requests.index', array_merge(
                                $request->query(),
                                ['page' => $highlightPage]
                            ));
                        }
                    }
                }

                $requests = $baseQuery
                    ->paginate($perPage)
                    ->withQueryString();

                $departments = Department::query()
                    ->orderBy('name')
                    ->get(['id', 'name']);

                return Inertia::render('Requests/AllRequests', [
                    'requests' => $requests,
                    'departments' => $departments,
                ]);
            })->name('index');
            Route::get('/history', function () {
                $logs = AuditLog::query()
                    ->where('auditable_type', Requests::class)
                    ->with([
                        'user:id,name,email',
                        'auditable' => function ($query) {
                            $query->select(['id', 'request_title', 'status']);
                        },
                    ])
                    ->latest()
                    ->paginate(20);

                return Inertia::render('Requests/RequestHistory', [
                    'logs' => $logs,
                ]);
            })->name('history');
        }
    );
Route::middleware(['auth', 'verified'])
    ->prefix('requests')
    ->name('requests.')
    ->group(function (): void {
        Route::get('/myrequests', function (Request $request) {
            $perPage = 20;
            $ownerId = Auth::id();

            $baseQuery = Requests::query()
                ->where('requested_by', $ownerId)
                ->with(['requestedBy:id,name,email', 'department:id,name', 'files', 'metadataTags:id,name,type'])
                ->latest();

            if ($request->filled('highlight') && ! $request->filled('page') && $ownerId !== null) {
                $highlightValue = trim((string) $request->query('highlight'));

                $highlightTarget = Requests::query()
                    ->select(['id', 'request_id', 'created_at'])
                    ->where('requested_by', $ownerId)
                    ->where('request_id', $highlightValue)
                    ->first();

                if ($highlightTarget !== null) {
                    $precedingCount = Requests::query()
                        ->where('requested_by', $ownerId)
                        ->where(function ($query) use ($highlightTarget) {
                            $query->where('created_at', '>', $highlightTarget->created_at)
                                ->orWhere(function ($inner) use ($highlightTarget) {
                                    $inner->where('created_at', $highlightTarget->created_at)
                                        ->where('id', '>', $highlightTarget->id);
                                });
                        })
                        ->count();

                    $highlightPage = (int) floor($precedingCount / $perPage) + 1;

                    if ($highlightPage > 1) {
                        return redirect()->route('requests.myrequests', array_merge(
                            $request->query(),
                            ['page' => $highlightPage]
                        ));
                    }
                }
            }

            $requests = $baseQuery
                ->paginate($perPage)
                ->withQueryString();

            $departments = Department::query()
                ->orderBy('name')
                ->get(['id', 'name']);

            return Inertia::render('Requests/MyRequest', [
                'requests' => $requests,
                'departments' => $departments,
            ]);
        })->name('myrequests');

    });
