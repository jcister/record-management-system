<?php

/** @var TestCase $this */

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

it('rejects unauthenticated notification sse requests', function (): void {
    $this->getJson(route('api.sse.notifications.updates'))->assertUnauthorized();
});

it('returns sse headers for authenticated notification stream without consuming body', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->get(route('api.sse.notifications.updates'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');
    $cacheControl = $response->headers->get('Cache-Control') ?? '';
    expect($cacheControl)->toContain('no-cache');
    expect($cacheControl)->toContain('no-store');
    $response->assertHeader('X-Accel-Buffering', 'no');
});
