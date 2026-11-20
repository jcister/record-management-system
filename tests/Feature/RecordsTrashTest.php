<?php

use App\Models\Folder;
use App\Models\User;

it('shows trashed child folders when parent remains active', function (): void {
    $user = User::factory()->create();

    grantOrganizeContentPermission($user);

    $parent = Folder::factory()->create([
        'created_by' => $user->id,
        'parent_id' => null,
        'is_basic' => true,
    ]);

    $child = Folder::factory()->create([
        'created_by' => $user->id,
        'parent_id' => $parent->id,
        'is_basic' => true,
    ]);

    $child->delete();

    $this->actingAs($user);

    $response = $this->get(route('records.trash'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Records/Trash')
        ->has('folders', 1)
        ->where('folders.0.id', $child->id)
    );
});

it('nests previously trashed child under newly trashed parent', function (): void {
    $user = User::factory()->create();

    grantOrganizeContentPermission($user);

    $parent = Folder::factory()->create([
        'created_by' => $user->id,
        'parent_id' => null,
        'is_basic' => true,
    ]);

    $child = Folder::factory()->create([
        'created_by' => $user->id,
        'parent_id' => $parent->id,
        'is_basic' => true,
    ]);

    $child->delete();

    $this->actingAs($user);

    $this->travel(2)->seconds();

    $parent->delete();

    $rootResponse = $this->get(route('records.trash'));

    $rootResponse->assertOk();
    $rootResponse->assertInertia(fn ($page) => $page
        ->component('Records/Trash')
        ->has('folders', 1)
        ->where('folders.0.id', $parent->id)
        ->where('folders', function ($folders) use ($child): bool {
            return ! collect($folders)->pluck('id')->contains($child->id);
        })
    );

    $nestedResponse = $this->get(route('records.trash.folder', $parent));

    $nestedResponse->assertOk();
    $nestedResponse->assertInertia(fn ($page) => $page
        ->component('Records/Trash')
        ->has('folders', 1)
        ->where('folders.0.id', $child->id)
    );
});

it('nests child folders when parent is trashed', function (): void {
    $user = User::factory()->create();

    grantOrganizeContentPermission($user);

    $parent = Folder::factory()->create([
        'created_by' => $user->id,
        'parent_id' => null,
        'is_basic' => true,
    ]);

    $child = Folder::factory()->create([
        'created_by' => $user->id,
        'parent_id' => $parent->id,
        'is_basic' => true,
    ]);

    $this->actingAs($user);

    $parent->delete();

    $rootResponse = $this->get(route('records.trash'));

    $rootResponse->assertOk();
    $rootResponse->assertInertia(fn ($page) => $page
        ->component('Records/Trash')
        ->has('folders', 1)
        ->where('folders.0.id', $parent->id)
    );

    $nestedResponse = $this->get(route('records.trash.folder', $parent));

    $nestedResponse->assertOk();
    $nestedResponse->assertInertia(fn ($page) => $page
        ->component('Records/Trash')
        ->has('folders', 1)
        ->where('folders.0.id', $child->id)
    );
});
