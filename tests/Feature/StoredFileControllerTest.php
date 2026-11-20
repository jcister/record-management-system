<?php

use App\Models\Department;
use App\Models\File;
use App\Models\Folder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $department = Department::factory()->create();
    $tag = Tag::factory()->physicalLocation()->create();
    $permission = Permission::firstOrCreate(
        ['slug' => 'upload-submit-reports'],
        ['name' => 'Upload Submit Reports']
    );
    $role = Role::factory()->create();
    $role->permissions()->attach($permission);
    $this->user->roles()->attach($role);

    $this->folder = Folder::factory()->create();
    $this->department = Department::factory()->create();
});

it('downloads a stored-only file via files download route', function (): void {
    $disk = config('filesystems.default', 'local');
    Storage::fake($disk);

    $path = 'private/uploads/'.uniqid().'_test.txt';
    $content = 'Hello stored file';
    Storage::disk($disk)->put($path, $content);

    $file = File::factory()->create([
        'uploaded_by' => $this->user->id,
        'folder_id' => $this->folder->id,
        'department_id' => $this->department->id,
        'is_record' => false,
        'record_id' => 'ATTACH-'.strtoupper(uniqid()),
        'file_path' => $path,
        'file_name' => 'test',
        'mime_type' => 'text/plain',
        'file_size' => strlen($content),
    ]);

    $response = $this->actingAs($this->user)->get("/api/files/{$file->id}/download");

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('test.txt');
    $response->assertHeader('Content-Length', (string) strlen($content));
});

it('returns 404 when stored-only file does not exist on disk', function (): void {
    $file = File::factory()->create([
        'uploaded_by' => $this->user->id,
        'folder_id' => $this->folder->id,
        'department_id' => $this->department->id,
        'is_record' => false,
        'record_id' => 'ATTACH-'.strtoupper(uniqid()),
        'file_path' => 'private/uploads/missing.txt',
        'file_name' => 'missing',
    ]);

    $response = $this->actingAs($this->user)->get("/api/files/{$file->id}/download");

    $response->assertNotFound();
});
