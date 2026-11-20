<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

function grantUploadPermission(User $user): void
{
    $uploadPermission = Permission::firstOrCreate(
        ['slug' => 'upload-submit-reports'],
        ['name' => 'Upload Submit Reports']
    );

    $role = Role::factory()->create();
    $role->permissions()->attach($uploadPermission);
    $user->roles()->attach($role);
}

it('can list all tags', function () {
    Tag::factory()->count(3)->create(['type' => 'metadata']);
    Tag::factory()->count(2)->create(['type' => 'physical_location']);

    $response = $this->getJson('/api/tags');

    $response->assertOk()
        ->assertJsonCount(5);
});

it('can filter tags by type', function () {
    Tag::factory()->count(3)->create(['type' => 'metadata']);
    Tag::factory()->count(2)->create(['type' => 'physical_location']);

    $response = $this->getJson('/api/tags?type=metadata');

    $response->assertOk()
        ->assertJsonCount(3);
});

it('can create metadata tags with upload permission', function () {
    grantUploadPermission($this->user);

    $response = $this->postJson('/api/tags', [
        'name' => 'Category A',
        'type' => 'metadata',
    ]);

    $response->assertCreated()
        ->assertJson([
            'name' => 'Category A',
            'type' => 'metadata',
        ]);

    $this->assertDatabaseHas('tags', [
        'name' => 'Category A',
        'type' => 'metadata',
    ]);
});

it('can create physical location tags with upload permission', function () {
    grantUploadPermission($this->user);

    $response = $this->postJson('/api/tags', [
        'name' => 'Shelf 2',
        'type' => 'physical_location',
    ]);

    $response->assertCreated()
        ->assertJson([
            'name' => 'Shelf 2',
            'type' => 'physical_location',
        ]);

    $this->assertDatabaseHas('tags', [
        'name' => 'Shelf 2',
        'type' => 'physical_location',
    ]);
});

it('returns existing tag when creating duplicate', function () {
    grantUploadPermission($this->user);

    $existing = Tag::factory()->create([
        'name' => 'Duplicate Tag',
        'type' => 'metadata',
    ]);

    $response = $this->postJson('/api/tags', [
        'name' => 'Duplicate Tag',
        'type' => 'metadata',
    ]);

    $response->assertOk()
        ->assertJson([
            'id' => $existing->id,
            'name' => 'Duplicate Tag',
            'type' => 'metadata',
        ]);
});

it('prevents unauthorized users from creating tags', function () {
    $response = $this->postJson('/api/tags', [
        'name' => 'Test Tag',
        'type' => 'metadata',
    ]);

    $response->assertForbidden();
});

it('maintains separate tag lists for metadata and physical location', function () {
    grantUploadPermission($this->user);

    // Create tags with the same name but different types
    $this->postJson('/api/tags', [
        'name' => 'Box A',
        'type' => 'metadata',
    ]);

    $this->postJson('/api/tags', [
        'name' => 'Box A',
        'type' => 'physical_location',
    ]);

    // Verify both exist independently
    $metadataTags = $this->getJson('/api/tags?type=metadata');
    $locationTags = $this->getJson('/api/tags?type=physical_location');

    $metadataTags->assertJsonFragment(['name' => 'Box A']);
    $locationTags->assertJsonFragment(['name' => 'Box A']);

    // Verify we have 2 separate tags
    expect(Tag::where('name', 'Box A')->count())->toBe(2);
});

it('can soft delete a tag with upload permission', function () {
    grantUploadPermission($this->user);

    $tag = Tag::factory()->create(['type' => 'metadata']);

    $response = $this->deleteJson("/api/tags/{$tag->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted('tags', ['id' => $tag->id]);
});

it('hides soft deleted tags from default index listing', function () {
    Tag::factory()->count(2)->create(['type' => 'metadata']);
    $deleted = Tag::factory()->create(['type' => 'metadata']);
    $deleted->delete();

    $response = $this->getJson('/api/tags?type=metadata');

    $response->assertOk()
        ->assertJsonCount(2);
});

it('includes soft deleted tags when with_trashed is requested', function () {
    Tag::factory()->count(2)->create(['type' => 'metadata']);
    $deleted = Tag::factory()->create(['type' => 'metadata']);
    $deleted->delete();

    $response = $this->getJson('/api/tags?type=metadata&with_trashed=1');

    $response->assertOk()
        ->assertJsonCount(3);
});

it('can restore a soft deleted tag with upload permission', function () {
    grantUploadPermission($this->user);

    $tag = Tag::factory()->create(['type' => 'metadata']);
    $tag->delete();

    $response = $this->postJson("/api/tags/{$tag->id}/restore");

    $response->assertOk()
        ->assertJson(['id' => $tag->id]);

    expect(Tag::find($tag->id))->not->toBeNull();
});

it('restores a soft deleted tag when creating a duplicate name and type', function () {
    grantUploadPermission($this->user);

    $tag = Tag::factory()->create(['name' => 'Archived Tag', 'type' => 'metadata']);
    $tag->delete();

    $response = $this->postJson('/api/tags', [
        'name' => 'Archived Tag',
        'type' => 'metadata',
    ]);

    $response->assertOk()
        ->assertJson(['id' => $tag->id]);

    expect(Tag::find($tag->id))->not->toBeNull();
});

it('prevents unauthorized users from deleting tags', function () {
    $tag = Tag::factory()->create(['type' => 'metadata']);

    $response = $this->deleteJson("/api/tags/{$tag->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('tags', ['id' => $tag->id, 'deleted_at' => null]);
});

it('prevents unauthorized users from restoring tags', function () {
    $tag = Tag::factory()->create(['type' => 'metadata']);
    $tag->delete();

    $response = $this->postJson("/api/tags/{$tag->id}/restore");

    $response->assertForbidden();
    $this->assertSoftDeleted('tags', ['id' => $tag->id]);
});
