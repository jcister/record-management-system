<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\Tag;
use App\Models\User;
use App\Support\RetentionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

it('returns at most five suggestions and indicates when more results exist', function (): void {
    $user = User::factory()->create();
    $searchTag = Tag::factory()->metadata()->create(['name' => 'ProjectNeedle']);

    $files = File::factory()
        ->count(6)
        ->sequence(fn ($sequence) => [
            'file_name' => sprintf('project report %02d', $sequence->index),
        ])
        ->state(fn () => ['status' => 'approved', 'retention_status' => RetentionStatus::ACTIVE])
        ->create();

    $files->each(fn (File $file) => $file->tags()->attach($searchTag->id));

    $this->actingAs($user);

    $response = $this->getJson('/search/suggestions?search=ProjectNeedle');

    $response->assertOk();

    expect($response->json('data'))->toHaveCount(5);
    expect($response->json('has_more'))->toBeTrue();
    expect(collect($response->json('data'))->every(fn (array $item) => $item['type'] === 'file'))->toBeTrue();
});

it('returns an empty payload when no search term is provided', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->getJson('/search/suggestions');

    $response->assertOk()->assertJson([
        'data' => [],
        'has_more' => false,
    ]);
});

it('includes matching folders in the suggestion payload', function (): void {
    $user = User::factory()->create();
    $searchTag = Tag::factory()->metadata()->create(['name' => 'AccountingNeedle']);

    $folder = Folder::factory()->create([
        'name' => 'AccountingNeedle Reports',
    ]);

    $file = File::factory()->create([
        'file_name' => 'Accounting Overview',
        'status' => 'approved',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);
    $file->tags()->attach($searchTag->id);

    $this->actingAs($user);

    $response = $this->getJson('/search/suggestions?search=AccountingNeedle');

    $response->assertOk();

    $data = collect($response->json('data'));

    expect($data->pluck('type'))->toContain('folder');

    $folderResult = $data->first(fn (array $item) => $item['type'] === 'folder');

    expect($folderResult['viewer_url'])->toBe(route('records.show', $folder));
});

it('returns parent folder metadata for file suggestions', function (): void {
    $user = User::factory()->create();
    $searchTag = Tag::factory()->metadata()->create(['name' => 'FinanceNeedle']);

    $folder = Folder::factory()->create([
        'name' => 'Finance Policies',
    ]);

    $file = File::factory()->for($folder)->create([
        'file_name' => 'Finance Policies 2026',
        'status' => 'approved',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);
    $file->tags()->attach($searchTag->id);

    $this->actingAs($user);

    $response = $this->getJson('/search/suggestions?search=FinanceNeedle');

    $response->assertOk();

    $fileResult = collect($response->json('data'))
        ->first(fn (array $item) => $item['type'] === 'file');

    if (is_null($fileResult)) {
        $this->fail('Expected at least one file suggestion to be present.');
    }

    expect($fileResult['folder'])->toMatchArray([
        'id' => $folder->id,
        'name' => $folder->name,
        'viewer_url' => route('records.show', $folder),
    ]);
});

it('returns only approved file suggestions', function (): void {
    $user = User::factory()->create();
    $searchTag = Tag::factory()->metadata()->create(['name' => 'Statusneedle']);

    $folder = Folder::factory()->create([
        'name' => 'Statusneedle Folder',
    ]);

    $approved = File::factory()->create([
        'file_name' => 'Statusneedle Approved',
        'remarks' => '{"target_file_record_id":"1234","target_file_name":"Statusneedle Approved"}',
        'status' => 'approved',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);
    $approved->tags()->attach($searchTag->id);

    $draft = File::factory()->create([
        'file_name' => 'Statusneedle Draft',
        'remarks' => '{"target_file_record_id":"2222","target_file_name":"Statusneedle Draft"}',
        'status' => 'draft',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);
    $draft->tags()->attach($searchTag->id);

    $pending = File::factory()->create([
        'file_name' => 'Statusneedle Pending',
        'remarks' => '{"target_file_record_id":"3333","target_file_name":"Statusneedle Pending"}',
        'status' => 'pending',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);
    $pending->tags()->attach($searchTag->id);

    $rejected = File::factory()->create([
        'file_name' => 'Statusneedle Rejected',
        'remarks' => '{"target_file_record_id":"4444","target_file_name":"Statusneedle Rejected"}',
        'status' => 'rejected',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);
    $rejected->tags()->attach($searchTag->id);

    $this->actingAs($user);

    $response = $this->getJson('/search/suggestions?search=Statusneedle&limit=5');

    $response->assertOk();

    $items = collect($response->json('data'));
    $fileItems = $items->where('type', 'file')->values();

    expect($fileItems)->toHaveCount(1);
    expect($fileItems->first()['file_name'])->toBe('Statusneedle Approved');
    expect($items->contains(fn (array $item) => $item['type'] === 'folder' && $item['id'] === $folder->id))->toBeTrue();
});
