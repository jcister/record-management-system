<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\Tag;
use App\Models\User;
use App\Support\RetentionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('includes folder results when searching', function (): void {
    $user = User::factory()->create();
    grantOrganizeContentPermission($user);
    $folder = Folder::factory()->create([
        'name' => 'Finance Needle Folder',
        'created_at' => now()->addMinute(),
    ]);

    $this->actingAs($user);

    $response = $this->get('/search?search=Needle');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Search')
        ->where('results.total', 1)
        ->where('results.data', function ($data) use ($folder): bool {
            return collect($data)->contains(fn (array $item) => $item['type'] === 'folder' && $item['id'] === $folder->id);
        })
    );
});

it('returns only approved files while keeping folder results', function (): void {
    $user = User::factory()->create();
    grantOrganizeContentPermission($user);

    $folder = Folder::factory()->create(['name' => 'StatusNeedle Folder']);
    $searchTag = Tag::factory()->metadata()->create(['name' => 'StatusNeedle']);

    $approvedFile = File::factory()->create([
        'file_name' => 'Status Approved File',
        'status' => 'approved',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);
    $approvedFile->tags()->attach($searchTag->id);

    $draftFile = File::factory()->create([
        'file_name' => 'Status Draft File',
        'status' => 'draft',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);
    $draftFile->tags()->attach($searchTag->id);

    $pendingFile = File::factory()->create([
        'file_name' => 'Status Pending File',
        'status' => 'pending',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);
    $pendingFile->tags()->attach($searchTag->id);

    $rejectedFile = File::factory()->create([
        'file_name' => 'Status Rejected File',
        'status' => 'rejected',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);
    $rejectedFile->tags()->attach($searchTag->id);

    $this->actingAs($user);

    $response = $this->get('/search?search=StatusNeedle');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Search')
        ->where('results.total', 2)
        ->where('results.data', function ($data) use ($approvedFile, $folder): bool {
            $items = collect($data);

            return $items->contains(fn (array $item) => $item['type'] === 'file' && $item['id'] === $approvedFile->id)
                && $items->contains(fn (array $item) => $item['type'] === 'folder' && $item['id'] === $folder->id)
                    && ! $items->contains(fn (array $item) => $item['type'] === 'file' && $item['name'] === 'Status Draft File')
                    && ! $items->contains(fn (array $item) => $item['type'] === 'file' && $item['name'] === 'Status Pending File')
                    && ! $items->contains(fn (array $item) => $item['type'] === 'file' && $item['name'] === 'Status Rejected File');
        })
    );
});
