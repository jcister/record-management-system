<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\User;

test('file without file_path has null file_path in folder view response', function () {
    $user = User::factory()->create();
    grantOrganizeContentPermission($user);

    $folder = Folder::factory()->create([
        'created_by' => $user->id,
        'parent_id' => null,
        'is_basic' => true,
    ]);

    $fileWithoutAttachment = File::factory()->create([
        'uploaded_by' => $user->id,
        'folder_id' => $folder->id,
        'file_path' => null,
        'ocr_status' => 'pending',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('records.show', $folder));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('FolderView')
        ->has('files', 1)
        ->where('files.0.id', $fileWithoutAttachment->id)
        ->where('files.0.file_path', null)
        ->where('files.0.ocr_status', 'pending')
    );
});

test('file without file_path never has ocr_status misrepresented — file_path remains null even when ocr_status is pending', function () {
    $user = User::factory()->create();
    grantOrganizeContentPermission($user);

    $folder = Folder::factory()->create([
        'created_by' => $user->id,
        'parent_id' => null,
        'is_basic' => true,
    ]);

    $noFilePending = File::factory()->create([
        'uploaded_by' => $user->id,
        'folder_id' => $folder->id,
        'file_path' => null,
        'ocr_status' => 'pending',
    ]);

    $noFileProcessing = File::factory()->create([
        'uploaded_by' => $user->id,
        'folder_id' => $folder->id,
        'file_path' => null,
        'ocr_status' => 'processing',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('records.show', $folder));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('FolderView')
        ->has('files', 2)
        ->where('files', fn ($files) => collect($files)->every(fn ($f) => $f['file_path'] === null))
    );
});

test('file with file_path and pending ocr_status exposes both fields for frontend badge transition', function () {
    $user = User::factory()->create();
    grantOrganizeContentPermission($user);

    $folder = Folder::factory()->create([
        'created_by' => $user->id,
        'parent_id' => null,
        'is_basic' => true,
    ]);

    $fileWithAttachment = File::factory()->create([
        'uploaded_by' => $user->id,
        'folder_id' => $folder->id,
        'file_path' => 'documents/test-file.pdf',
        'ocr_status' => 'pending',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('records.show', $folder));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('FolderView')
        ->has('files', 1)
        ->where('files.0.id', $fileWithAttachment->id)
        ->where('files.0.ocr_status', 'pending')
        ->where('files.0.file_path', 'documents/test-file.pdf')
    );
});
