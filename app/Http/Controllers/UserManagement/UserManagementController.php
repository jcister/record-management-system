<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManagedUserRequest;
use App\Mail\UserCreatedNotification;
use App\Models\Department;
use App\Models\Positions;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function create(): Response
    {
        $roles = Role::query()->orderBy('name')->get(['id', 'name']);
        $positions = Positions::query()->withTrashed()->orderBy('name')->get(['id', 'name', 'deleted_at']);
        $departments = Department::query()->withTrashed()->orderBy('name')->get(['id', 'name', 'deleted_at']);
        $units = Unit::query()->withTrashed()->orderBy('name')->get(['id', 'name', 'deleted_at']);

        return Inertia::render('UserManagement/Provisioning', [
            'roles' => $roles,
            'positions' => $positions,
            'departments' => $departments,
            'units' => $units,
        ]);
    }

    public function index(): RedirectResponse
    {
        return redirect()->route('user-management.directory');
    }

    public function storeUser(StoreManagedUserRequest $request): RedirectResponse
    {
        $payload = $request->validated();

        $temporaryPassword = Str::random(16);

        $user = User::query()->create([
            'name' => $payload['name'],
            'prefix' => $payload['prefix'] ?? null,
            'first_name' => $payload['first_name'],
            'middle_name' => $payload['middle_name'] ?? null,
            'last_name' => $payload['last_name'],
            'extension' => $payload['extension'] ?? null,
            'email' => $payload['email'],
            'password' => Hash::make($temporaryPassword),
            'email_verified_at' => now(),
            'password_reset_required' => true,
            'department_id' => $payload['department_id'] ?? null,
            'unit_id' => $payload['unit_id'] ?? null,
            'position_id' => $payload['position_id'] ?? null,
        ]);

        $roleIds = $payload['role_ids'] ?? [];

        $this->syncUserRoles($user, $roleIds);

        $loginUrl = route('login');
        Mail::to($user->email)->send(new UserCreatedNotification($user, $temporaryPassword, $loginUrl));

        $this->audit->log(
            'user_created',
            $request->user()?->name.' created user "'.$user->name.'" and sent temporary password via email.',
            $user
        );

        return back()->with('flash.banner', 'User account created and email notification sent.');
    }

    private function syncUserRoles(User $user, array $roleIds): void
    {
        $ids = collect($roleIds)->filter()->all();

        if ($ids === []) {
            $defaultRoleId = Role::query()->where('slug', 'viewer')->value('id');

            if ($defaultRoleId) {
                $ids = [$defaultRoleId];
            }
        }

        $ids = Role::query()
            ->whereIn('id', $ids)
            ->where('slug', '!=', 'super-admin')
            ->pluck('id')
            ->all();

        $superAdminRoleId = Role::query()->where('slug', 'super-admin')->value('id');

        if ($superAdminRoleId && $user->roles()->whereKey($superAdminRoleId)->exists()) {
            $ids[] = $superAdminRoleId;
        }

        $user->roles()->sync(array_values(array_unique($ids)));
    }
}
