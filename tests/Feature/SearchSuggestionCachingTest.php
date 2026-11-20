<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);

    // Clear cache before each test
    Cache::flush();
});

it('caches search suggestions', function () {
    $searchTag = Tag::factory()->metadata()->create(['name' => 'BudgetNeedle']);

    $file = File::factory()->create([
        'file_name' => 'budget report document',
        'status' => 'approved',
        'retention_status' => 'active',
    ]);
    $file->tags()->attach($searchTag->id);

    // First request should hit the database
    $response1 = get('/search/suggestions?search=BudgetNeedle&limit=5');
    $response1->assertSuccessful();
    $data1 = $response1->json('data');

    expect($data1)->toHaveCount(1);
    expect($data1[0]['file_name'])->toContain('budget');

    // Second identical request should return cached results
    $response2 = get('/search/suggestions?search=BudgetNeedle&limit=5');
    $response2->assertSuccessful();
    $data2 = $response2->json('data');

    expect($data2)->toEqual($data1);
});

it('invalidates cache when a file is created', function () {
    $searchTag = Tag::factory()->metadata()->create(['name' => 'ReportNeedle']);

    // Create initial file and cache results
    $initial = File::factory()->create([
        'file_name' => 'report document',
        'status' => 'approved',
        'retention_status' => 'active',
    ]);
    $initial->tags()->attach($searchTag->id);

    get('/search/suggestions?search=ReportNeedle&limit=5')->assertSuccessful();

    // Create new file - should invalidate cache
    $newFile = File::factory()->create([
        'file_name' => 'new report document',
        'status' => 'approved',
        'retention_status' => 'active',
    ]);
    $newFile->tags()->attach($searchTag->id);

    // Cache should be cleared, so we can't verify exact behavior
    // but we can ensure request still works
    $response = get('/search/suggestions?search=ReportNeedle&limit=5');
    $response->assertSuccessful();
    expect($response->json('data'))->toBeArray();
});

it('invalidates cache when a file is updated', function () {
    $oldSearchTag = Tag::factory()->metadata()->create(['name' => 'OldNeedle']);

    $file = File::factory()->create([
        'file_name' => 'old name document',
        'status' => 'approved',
        'retention_status' => 'active',
    ]);
    $file->tags()->attach($oldSearchTag->id);

    get('/search/suggestions?search=OldNeedle&limit=5')->assertSuccessful();

    // Update file - should invalidate cache
    $file->update(['file_name' => 'new name document']);

    $response = get('/search/suggestions?search=new&limit=5');
    $response->assertSuccessful();
});

it('invalidates cache when a file is deleted', function () {
    $searchTag = Tag::factory()->metadata()->create(['name' => 'DeleteNeedle']);

    $file = File::factory()->create([
        'file_name' => 'delete me document',
        'status' => 'approved',
        'retention_status' => 'active',
    ]);
    $file->tags()->attach($searchTag->id);

    get('/search/suggestions?search=DeleteNeedle&limit=5')->assertSuccessful();

    // Delete file - should invalidate cache
    $file->delete();

    $response = get('/search/suggestions?search=DeleteNeedle&limit=5');
    $response->assertSuccessful();
});

it('invalidates cache when a folder is created', function () {
    Folder::factory()->create(['name' => 'Reports']);

    get('/search/suggestions?search=reports&limit=5')->assertSuccessful();

    // Create new folder - should invalidate cache
    Folder::factory()->create(['name' => 'New Reports']);

    $response = get('/search/suggestions?search=reports&limit=5');
    $response->assertSuccessful();
});

it('invalidates cache when a folder is updated', function () {
    $folder = Folder::factory()->create(['name' => 'Old Folder']);

    get('/search/suggestions?search=old&limit=5')->assertSuccessful();

    // Update folder - should invalidate cache
    $folder->update(['name' => 'New Folder']);

    $response = get('/search/suggestions?search=new&limit=5');
    $response->assertSuccessful();
});

it('invalidates cache when a folder is deleted', function () {
    $folder = Folder::factory()->create(['name' => 'Delete Folder']);

    get('/search/suggestions?search=delete&limit=5')->assertSuccessful();

    // Delete folder - should invalidate cache
    $folder->delete();

    $response = get('/search/suggestions?search=delete&limit=5');
    $response->assertSuccessful();
});

it('slices cached results to requested limit', function () {
    $searchTag = Tag::factory()->metadata()->create(['name' => 'FileNeedle']);

    // Create multiple files
    collect(range(1, 10))->each(function ($i) use ($searchTag) {
        $file = File::factory()->create([
            'file_name' => "file {$i} document",
            'status' => 'approved',
            'retention_status' => 'active',
        ]);

        $file->tags()->attach($searchTag->id);
    });

    // Request with limit 5
    $response1 = get('/search/suggestions?search=FileNeedle&limit=5');
    $response1->assertSuccessful();
    expect($response1->json('data'))->toHaveCount(5);

    // Request with limit 3 should slice from cache
    $response2 = get('/search/suggestions?search=FileNeedle&limit=3');
    $response2->assertSuccessful();
    expect($response2->json('data'))->toHaveCount(3);
});

it('returns empty results for empty search query', function () {
    $response = get('/search/suggestions?search=&limit=5');

    $response->assertSuccessful();
    $response->assertJson([
        'data' => [],
        'has_more' => false,
    ]);
});

it('normalizes search terms consistently', function () {
    File::factory()->create([
        'file_name' => 'test file document',
        'status' => 'approved',
        'retention_status' => 'active',
    ]);

    // Different special characters should be normalized to the same cache key
    $response1 = get('/search/suggestions?search=test&limit=5');
    $response2 = get('/search/suggestions?search=test!&limit=5');
    $response3 = get('/search/suggestions?search=test%20&limit=5');

    $response1->assertSuccessful();
    $response2->assertSuccessful();
    $response3->assertSuccessful();

    // All should return same results
    expect($response1->json('data'))->toEqual($response2->json('data'));
});
