<?php

use App\Models\User;

it('returns users for authenticated users', function () {
    $authUser = User::factory()->create();

    User::factory()->create(['name' => 'Alice Smith']);
    User::factory()->create(['name' => 'Bob Johnson']);
    User::factory()->create(['name' => 'Charlie Brown']);

    $response = $this->actingAs($authUser)
        ->getJson('/api/users');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name'],
            ],
        ])
        ->assertJsonCount(4, 'data'); // 3 created + 1 auth user
});

it('returns users ordered by name', function () {
    $authUser = User::factory()->create(['name' => 'Zebra User']);

    User::factory()->create(['name' => 'Alice User']);
    User::factory()->create(['name' => 'Bob User']);

    $response = $this->actingAs($authUser)
        ->getJson('/api/users');

    $response->assertOk();

    $users = $response->json('data');
    expect($users[0]['name'])->toBe('Alice User')
        ->and($users[1]['name'])->toBe('Bob User')
        ->and($users[2]['name'])->toBe('Zebra User');
});

it('requires authentication', function () {
    $response = $this->getJson('/api/users');

    $response->assertUnauthorized();
});
