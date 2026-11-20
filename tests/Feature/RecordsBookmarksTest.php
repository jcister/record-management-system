<?php

use App\Models\Bookmark;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Support\RetentionStatus;

test('bookmarked folders include item counts', function (): void {
    $user = User::factory()->create();
    grantOrganizeContentPermission($user);

    $folder = Folder::factory()->create([
        'created_by' => $user->id,
        'parent_id' => null,
        'is_basic' => true,
    ]);

    Folder::factory()->create([
        'created_by' => $user->id,
        'parent_id' => $folder->id,
        'is_basic' => true,
    ]);

    File::factory()->create([
        'folder_id' => $folder->id,
        'uploaded_by' => $user->id,
        'retention_status' => RetentionStatus::ACTIVE,
    ]);

    Bookmark::factory()->create([
        'user_id' => $user->id,
        'bookmarkable_type' => Folder::class,
        'bookmarkable_id' => $folder->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('records.bookmarks'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Records/Bookmarks')
        ->where('bookmarks.0.bookmarkable.children_count', 1)
        ->where('bookmarks.0.bookmarkable.files_count', 1)
    );
});
