<?php

use App\Models\Requests;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects to the highlight page when the target request is not on the first page', function (): void {
    $user = User::factory()->create();

    $highlightTarget = null;

    foreach (range(1, 25) as $index) {
        $request = Requests::factory()->create([
            'request_id' => sprintf('req-%02d', $index),
            'created_at' => now()->subMinutes($index),
        ]);

        if ($index === 25) {
            $highlightTarget = $request;
        }
    }

    $response = $this->actingAs($user)
        ->get(route('requests.index', [
            'highlight' => $highlightTarget?->request_id,
        ]));

    $response->assertRedirect(route('requests.index', [
        'highlight' => $highlightTarget?->request_id,
        'page' => 2,
    ]));
});

it('keeps the user on the current page when the highlight already exists there', function (): void {
    $user = User::factory()->create();

    $latestRequest = null;

    foreach (range(1, 5) as $index) {
        $latestRequest = Requests::factory()->create([
            'request_id' => sprintf('req-%02d', $index),
            'created_at' => now()->subMinutes($index),
        ]);
    }

    $response = $this->actingAs($user)
        ->get(route('requests.index', [
            'highlight' => $latestRequest?->request_id,
        ]));

    $response->assertOk();
});

it('includes requester details on the my requests payload', function (): void {
    $user = User::factory()->create();

    Requests::factory()->create([
        'requested_by' => $user->id,
    ]);

    // Create a request belonging to a different user to ensure filtering is correct
    Requests::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('requests.myrequests'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Requests/MyRequest')
        ->where('requests.data.0.requested_by.id', $user->id)
        ->where('requests.data.0.requested_by.name', $user->name)
        ->where('requests.data.0.requested_by.email', $user->email)
    );
});
