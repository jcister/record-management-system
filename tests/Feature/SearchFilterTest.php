<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->user = User::factory()->create();
    grantOrganizeContentPermission($this->user);
    actingAs($this->user);
});

it('returns filter options without caching', function () {
    // Create initial data
    $rootFolder1 = Folder::factory()->create(['parent_id' => null, 'is_basic' => true, 'name' => 'CAV']);
    $rootFolder2 = Folder::factory()->create(['parent_id' => null, 'is_basic' => true, 'name' => 'COPC']);

    $metadataTag1 = Tag::factory()->metadata()->create(['name' => 'Budget']);
    $physicalTag1 = Tag::factory()->physicalLocation()->create(['name' => 'Cabinet A']);

    File::factory()->create(['author' => 'John Doe', 'retention_status' => 'active']);

    // First request
    $response1 = get('/api/search/filter-options');
    $response1->assertSuccessful();
    $data1 = $response1->json();

    expect($data1['rootFolders'])->toHaveCount(2);
    expect($data1['authors'])->toHaveCount(1);
    expect($data1['metadataTags'])->toHaveCount(1);
    expect($data1['physicalLocationTags'])->toHaveCount(1);

    // Add more data
    Folder::factory()->create(['parent_id' => null, 'is_basic' => true, 'name' => 'EL/PR']);
    Tag::factory()->metadata()->create(['name' => 'Reports']);
    Tag::factory()->physicalLocation()->create(['name' => 'Cabinet B']);
    File::factory()->create(['author' => 'Jane Smith', 'retention_status' => 'active']);

    // Second request should reflect new data (no caching)
    $response2 = get('/api/search/filter-options');
    $response2->assertSuccessful();
    $data2 = $response2->json();

    expect($data2['rootFolders'])->toHaveCount(3);
    expect($data2['authors'])->toHaveCount(2);
    expect($data2['metadataTags'])->toHaveCount(2);
    expect($data2['physicalLocationTags'])->toHaveCount(2);
});

it('returns only unique authors', function () {
    File::factory()->create(['author' => 'John Doe', 'retention_status' => 'active']);
    File::factory()->create(['author' => 'John Doe', 'retention_status' => 'active']);
    File::factory()->create(['author' => 'Jane Smith', 'retention_status' => 'active']);

    $response = get('/api/search/filter-options');
    $authors = $response->json('authors');

    expect($authors)->toHaveCount(2);
    expect(collect($authors)->pluck('name')->toArray())->toBe(['Jane Smith', 'John Doe']);
});

it('returns only root folders that are basic', function () {
    $rootBasic = Folder::factory()->create(['parent_id' => null, 'is_basic' => true, 'name' => 'Basic Root']);
    $rootCustom = Folder::factory()->create(['parent_id' => null, 'is_basic' => false, 'name' => 'Custom Root']);
    $childFolder = Folder::factory()->create(['parent_id' => $rootBasic->id, 'is_basic' => true, 'name' => 'Child']);

    $response = get('/api/search/filter-options');
    $rootFolders = $response->json('rootFolders');

    expect($rootFolders)->toHaveCount(1);
    expect($rootFolders[0]['name'])->toBe('Basic Root');
});

it('returns metadata tags sorted by name', function () {
    Tag::factory()->metadata()->create(['name' => 'Zebra']);
    Tag::factory()->metadata()->create(['name' => 'Apple']);
    Tag::factory()->metadata()->create(['name' => 'Middle']);
    Tag::factory()->physicalLocation()->create(['name' => 'Should Not Appear']);

    $response = get('/api/search/filter-options');
    $metadataTags = $response->json('metadataTags');

    expect($metadataTags)->toHaveCount(3);
    expect(collect($metadataTags)->pluck('name')->toArray())->toBe(['Apple', 'Middle', 'Zebra']);
});

it('returns physical location tags sorted by name', function () {
    Tag::factory()->physicalLocation()->create(['name' => 'Zone C']);
    Tag::factory()->physicalLocation()->create(['name' => 'Zone A']);
    Tag::factory()->physicalLocation()->create(['name' => 'Zone B']);
    Tag::factory()->metadata()->create(['name' => 'Should Not Appear']);

    $response = get('/api/search/filter-options');
    $physicalTags = $response->json('physicalLocationTags');

    expect($physicalTags)->toHaveCount(3);
    expect(collect($physicalTags)->pluck('name')->toArray())->toBe(['Zone A', 'Zone B', 'Zone C']);
});

it('returns correct retention duration options', function () {
    $response = get('/api/search/filter-options');
    $retentionDurations = $response->json('retentionDurations');

    expect($retentionDurations)->toHaveCount(3);
    expect($retentionDurations)->toBe([
        ['value' => 'one_year', 'label' => '1 Year'],
        ['value' => 'ten_years', 'label' => '10 Years'],
        ['value' => 'perpetual', 'label' => 'Perpetual'],
    ]);
});

it('includes filter fields in search results', function () {
    $rootFolder = Folder::factory()->create(['parent_id' => null, 'is_basic' => true]);
    $subfolder = Folder::factory()->create(['parent_id' => $rootFolder->id]);

    $metadataTag = Tag::factory()->metadata()->create(['name' => 'FilterNeedleTag']);
    $physicalTag = Tag::factory()->physicalLocation()->create();

    $file = File::factory()->create([
        'folder_id' => $subfolder->id,
        'file_name' => 'filterneedle-document.pdf',
        'status' => 'approved',
        'author' => 'FilterNeedleAuthor',
        'retention_duration' => 'one_year',
        'is_record' => true,
        'retention_status' => 'active',
        'date_created' => now(),
        'date_released' => now(),
    ]);

    $file->tags()->attach([$metadataTag->id, $physicalTag->id]);

    $response = get('/search?search=FilterNeedleTag');
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Search')
        ->has('results.data', 1)
        ->has('results.data.0', fn (AssertableInertia $item) => $item
            ->where('type', 'file')
            ->has('author')
            ->has('retention_duration')
            ->has('is_record')
            ->has('root_folder_id')
            ->has('metadata_tag_ids')
            ->has('physical_location_tag_ids')
            ->etc()
        )
    );
});

it('correctly identifies root folder id for nested folders', function () {
    $rootFolder = Folder::factory()->create(['parent_id' => null, 'is_basic' => true, 'name' => 'Root']);
    $level1 = Folder::factory()->create(['parent_id' => $rootFolder->id, 'name' => 'Level 1']);
    $level2 = Folder::factory()->create(['parent_id' => $level1->id, 'name' => 'Level 2']);
    $level3 = Folder::factory()->create(['parent_id' => $level2->id, 'name' => 'Level 3']);

    $metadataTag = Tag::factory()->metadata()->create(['name' => 'RootNeedleTag']);

    $file = File::factory()->create([
        'folder_id' => $level3->id,
        'file_name' => 'nested-root.pdf',
        'status' => 'approved',
        'retention_status' => 'active',
    ]);
    $file->tags()->attach($metadataTag->id);

    $response = get('/search?search=RootNeedleTag');
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Search')
        ->has('results.data', 1)
        ->where('results.data.0.root_folder_id', $rootFolder->id)
    );
});

it('handles files without folders gracefully', function () {
    $metadataTag = Tag::factory()->metadata()->create(['name' => 'NoFolderNeedleTag']);

    $file = File::factory()->create([
        'folder_id' => null,
        'file_name' => 'no-folder.pdf',
        'status' => 'approved',
        'retention_status' => 'active',
    ]);
    $file->tags()->attach($metadataTag->id);

    $response = get('/search?search=NoFolderNeedleTag');
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Search')
        ->has('results.data', 1)
        ->where('results.data.0.root_folder_id', null)
    );
});
