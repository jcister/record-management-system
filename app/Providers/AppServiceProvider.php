<?php

namespace App\Providers;

use App\Models\Bookmark;
use App\Models\Department;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Folder;
use App\Models\Institution;
use App\Models\Permission;
use App\Models\Requests;
use App\Models\Role;
use App\Models\User;
use App\Observers\BookmarkObserver;
use App\Observers\DepartmentObserver;
use App\Observers\FileObserver;
use App\Observers\FileVersionObserver;
use App\Observers\FolderObserver;
use App\Observers\InstitutionObserver;
use App\Observers\PermissionObserver;
use App\Observers\RequestsObserver;
use App\Observers\RoleObserver;
use App\Observers\UserObserver;
use App\Policies\RequestPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureObservers();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): Password => Password::min(8)
            ->mixedCase()
            ->letters()
            ->numbers()
            ->symbols()
            ->uncompromised()
        );
    }

    protected function configureAuthorization(): void
    {
        // Model policies
        Gate::policy(Requests::class, RequestPolicy::class);

        // Permission-based gates
        Gate::define('manage-users', function (?User $user): bool {
            return $user?->hasPermission('user-management') ?? false;
        });

        Gate::define('organize-content', function (?User $user): bool {
            return $user?->hasPermission('organize-content') ?? false;
        });

        Gate::define('delete-content', function (?User $user): bool {
            return $user?->hasPermission('delete-content') ?? false;
        });

        Gate::define('review-content', function (?User $user): bool {
            return $user?->hasPermission('review-content') ?? false;
        });

        Gate::define('disposal-management', function (?User $user): bool {
            return $user?->hasPermission('disposal-management') ?? false;
        });
        Gate::define('manage-requests', function (?User $user): bool {
            return $user?->hasPermission('manage-requests') ?? false;
        });
        Gate::define('manage-reports', function (?User $user): bool {
            return $user?->hasPermission('manage-reports') ?? false;
        });
        Gate::define('manage-agency-information', function (?User $user): bool {
            return $user?->hasPermission('manage-agency-information') ?? false;
        });

        Gate::define('manage-log', function (?User $user): bool {
            return $user?->hasPermission('user-management') ?? false;
        });

        Gate::define('view-audit-trail', function (?User $user): bool {
            return $user?->hasPermission('view-audit-trail') ?? false;
        });
    }

    protected function configureObservers(): void
    {
        File::observe(FileObserver::class);
        FileVersion::observe(FileVersionObserver::class);
        Folder::observe(FolderObserver::class);
        Role::observe(RoleObserver::class);
        Permission::observe(PermissionObserver::class);
        Institution::observe(InstitutionObserver::class);
        Bookmark::observe(BookmarkObserver::class);
        User::observe(UserObserver::class);
        Department::observe(DepartmentObserver::class);
        Requests::observe(RequestsObserver::class);
    }
}
