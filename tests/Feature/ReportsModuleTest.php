<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('reports index remains available without overdue and retention compliance entries', function (): void {
    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('reports.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Reports/Index'));
});

test('removed reports routes are not available', function (): void {
    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get('/reports/overdue-records')->assertNotFound();
    $this->get('/reports/retention-compliance')->assertNotFound();
});

test('removed report types can no longer be exported', function (): void {
    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get('/reports/export?type=overdue-records&format=pdf')
        ->assertRedirect()
        ->assertSessionHasErrors(['type']);

    $this->get('/reports/export?type=retention-compliance&format=excel')
        ->assertRedirect()
        ->assertSessionHasErrors(['type']);
});
