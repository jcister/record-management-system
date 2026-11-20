<?php

namespace App\Policies;

use App\Models\Requests;
use App\Models\User;

class RequestPolicy
{
    /**
     * Determine if the user can update the request.
     * User must be the request owner OR have manage-requests permission.
     * Request must not have any uploaded scans AND status must not be terminal.
     */
    public function update(User $user, Requests $request): bool
    {
        // Check if user is the request owner or has manage-requests permission
        $hasPermission = $request->requested_by === $user->id
            || $user->hasPermission('manage-requests');

        if (! $hasPermission) {
            return false;
        }

        // Check if request is in a terminal state
        if (in_array($request->status, ['Complete', 'Rejected'], true)) {
            return false;
        }

        // Check if request has any uploaded scans (files with SCAN- prefix in record_id)
        $hasScans = $request->files()
            ->where(function ($query) {
                $query->where('record_id', 'like', 'SCAN-%')
                    ->orWhere('record_id', 'like', 'scan-%');
            })
            ->exists();

        if ($hasScans) {
            return false;
        }

        return true;
    }
}
