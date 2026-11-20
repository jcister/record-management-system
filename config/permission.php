<?php

return [
    'models' => [
        'role' => App\Models\Role::class,
        'permission' => App\Models\Permission::class,
    ],

    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],

    'column_names' => [
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'team_id',
        'role_pivot_key' => 'role_id',
        'permission_pivot_key' => 'permission_id',
    ],

    'teams' => (bool) env('PERMISSION_TEAMS', false),

    'testing' => (bool) env('PERMISSION_TESTING', false),

    'cache' => [
        'expiration_time' => 60 * 60,
        'key' => 'permission.cache',
        'store' => env('PERMISSION_CACHE_DRIVER', 'default'),
    ],
];
