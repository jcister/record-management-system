<?php

/** @var \Tests\TestCase $this */

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects unauthenticated api requests', function () {
    $this->getJson('/api/bookmarks')->assertUnauthorized();
});

it('allows sanctum-authenticated users to access api routes', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/bookmarks')->assertOk();
});
