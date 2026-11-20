<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Services\SearchExperienceService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user !== null) {
            $user->loadMissing(['roles:id,name,slug', 'position:id,name']);
        }

        $userPayload = $user === null
            ? null
            : [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar ? '/storage/'.$user->avatar : null,
                'email_verified_at' => $user->email_verified_at,
                'department' => $user->department ? [
                    'id' => $user->department->id,
                    'name' => $user->department->name,
                ] : null,
                'unit' => $user->unit ? [
                    'id' => $user->unit->id,
                    'name' => $user->unit->name,
                ] : null,
                'position' => $user->position ? [
                    'id' => $user->position->id,
                    'name' => $user->position->name,
                ] : null,
                'sidebar_icon_color' => $user->sidebar_icon_color,
                'contact_number' => $user->contact_number,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'roles' => $user->roles
                    ->map(fn (Role $role) => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'slug' => $role->slug,
                    ])
                    ->values(),
            ];

        /** @var SearchExperienceService $searchExperience */
        $searchExperience = app(SearchExperienceService::class);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'subtitle' => config('app.subtitle'),
            'auth' => [
                'user' => $userPayload,
                'can' => [
                    'manageUsers' => $request->user()?->can('manage-users') ?? false,
                    'canDeleteUsers' => $request->user()?->roles()->whereIn('slug', ['super-admin'])->exists() ?? false,
                    'organizeContent' => $request->user()?->can('organize-content') ?? false,
                    'deleteContent' => $request->user()?->can('delete-content') ?? false,
                    'reviewContent' => $request->user()?->can('review-content') ?? false,
                    'manageDisposal' => $request->user()?->can('disposal-management') ?? false,
                    'manageRequests' => $request->user()?->can('manage-requests') ?? false,
                    'manageReports' => $request->user()?->can('manage-reports') ?? false,
                    'manageAgencyInformation' => $request->user()?->can('manage-agency-information') ?? false,
                    'viewAuditTrail' => $request->user()?->can('view-audit-trail') ?? false,
                ],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'searchPanel' => $user === null
                ? ['frequently_accessed' => []]
                : $searchExperience->getSharedData(),
            'unreadNotificationsCount' => $user?->unreadNotifications()->count() ?? 0,
        ];
    }
}
