<?php

/** @var \Tests\TestCase $this */

use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('stores a bookmark for the current user', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $folder = Folder::factory()->create();

    $response = actingAs($user)->postJson('/api/bookmarks', [
        'bookmarkable_type' => Folder::class,
        'bookmarkable_id' => $folder->id,
    ]);

    $response->assertCreated()->assertJsonPath('user_id', $user->id);

    expect(Bookmark::first())
        ->bookmarkable_id->toBe($folder->id)
        ->user_id->toBe($user->id);
});

it('prevents viewing bookmarks that belong to another user', function () {
    $bookmark = Bookmark::factory()->create();
    /** @var User $otherUser */
    $otherUser = User::factory()->create();

    actingAs($otherUser)
        ->getJson("/api/bookmarks/{$bookmark->id}")
        ->assertForbidden();
});
