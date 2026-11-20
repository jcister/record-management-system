<?php

use App\Models\File;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;

use function Pest\Laravel\actingAs;

function grantManageReportsPermission(User $user): void
{
    $permission = Permission::firstOrCreate(
        ['slug' => 'manage-reports'],
        ['name' => 'Manage Reports']
    );

    $role = Role::factory()->create();
    $role->permissions()->syncWithoutDetaching([$permission->id]);
    $user->roles()->attach($role);
}

test('records inventory can be searched by metadata tag name', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    grantManageReportsPermission($user);
    actingAs($user);

    $tag = Tag::factory()->create([
        'type' => 'metadata',
        'name' => 'ALPHA_META',
    ]);

    $matching = File::factory()->create();
    $matching->tags()->syncWithoutDetaching([$tag->id]);

    File::factory()->create();

    $this->get('/reports/records-inventory?metadata_tag_search=ALPHA')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/RecordsInventory')
            ->where('filters.metadata_tag_search', 'ALPHA')
            ->has('records.data', 1)
            ->where('records.data.0.id', $matching->id)
        );
});

test('records inventory can be searched by physical location tag name', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    grantManageReportsPermission($user);
    actingAs($user);

    $tag = Tag::factory()->create([
        'type' => 'physical_location',
        'name' => 'Shelf 9',
    ]);

    $matching = File::factory()->create();
    $matching->tags()->syncWithoutDetaching([$tag->id]);

    File::factory()->create();

    $this->get('/reports/records-inventory?physical_location_tag_search=Shelf')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/RecordsInventory')
            ->where('filters.physical_location_tag_search', 'Shelf')
            ->has('records.data', 1)
            ->where('records.data.0.id', $matching->id)
        );
});
