<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\User;

test('records index provides folders and files', function () {
    $user = User::factory()->create();
    grantOrganizeContentPermission($user);

    Folder::factory()->count(2)->create([
        'created_by' => $user->id,
        'parent_id' => null,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('records.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Records/AllRecords')
        ->has('folders')
        ->has('files')
    );
});

test('pending and unorganized separates scan review files from regular files', function () {
    $user = User::factory()->create();
    grantOrganizeContentPermission($user);

    $regularFile = File::factory()->create([
        'uploaded_by' => $user->id,
        'folder_id' => null,
        'record_id' => 'REC-'.strtoupper(uniqid()),
        'status' => 'approved',
    ]);

    $scanReviewFile = File::factory()->create([
        'uploaded_by' => $user->id,
        'folder_id' => null,
        'record_id' => 'SCAN-'.strtoupper(uniqid()),
        'status' => 'pending',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('records.pending-unorganized'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Records/Pending&Unorganized')
        ->has('files', 2)
        ->where('files', fn ($files) => collect($files)->contains('id', $regularFile->id))
        ->where('files', fn ($files) => collect($files)->contains('id', $scanReviewFile->id))
    );
});
