<?php

use App\Models\User;

test('legacy notifications route redirects to account settings notifications', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('notifications.index'));

    $response->assertRedirect(route('user-notifications.index', absolute: false));
});
