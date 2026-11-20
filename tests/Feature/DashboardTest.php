<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    grantOrganizeContentPermission($user);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard displays folders and files', function () {
    $user = User::factory()->create();
    grantOrganizeContentPermission($user);
    $folder = Folder::factory()->create(['created_by' => $user->id]);
    $file = File::factory()->create(['uploaded_by' => $user->id]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('folders')
        ->has('files')
    );
});
