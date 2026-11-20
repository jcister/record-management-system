<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('debugs what disposal management actually returns', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    $response = $this->get('/disposal-management');

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Disposal/DisposalManagement')
        ->has('queue')
        ->has('queue.data')
        ->has('filters')
        ->has('options')
        ->has('metrics')
        ->dump('queue.data', 'filters', 'metrics.totals')
    );
});
