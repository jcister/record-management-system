<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('lookup management page requires authentication', function (): void {
    $this->get(route('systemsettings.lookup-management'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view lookup management page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('systemsettings.lookup-management'))
        ->assertInertia(fn (Assert $page) => $page->component('systemsettings/LookupManagement'));
});
