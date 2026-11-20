<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('force password reset screen can be rendered when required', function () {
    $user = User::factory()->create([
        'password_reset_required' => true,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('force-password-reset.show'));
});

test('force password reset screen can be accessed directly', function () {
    $user = User::factory()->create([
        'password_reset_required' => true,
    ]);

    $response = $this->actingAs($user)->get(route('force-password-reset.show'));

    $response->assertOk();
});

test('password reset redirects default users to my requests', function () {
    $user = User::factory()->create([
        'password' => Hash::make('OldT3st!Pass99'),
        'password_reset_required' => true,
    ]);

    $response = $this->actingAs($user)->post(route('force-password-reset.update'), [
        'password' => 'ValidT3st!Pass99',
        'password_confirmation' => 'ValidT3st!Pass99',
    ]);

    $response->assertRedirect(route('requests.myrequests'));

    $user->refresh();

    expect($user->password_reset_required)->toBeFalse();
    expect(Hash::check('ValidT3st!Pass99', $user->password))->toBeTrue();
});

test('password reset redirects organizers to the dashboard', function () {
    $user = User::factory()->create([
        'password' => Hash::make('OldT3st!Pass99'),
        'password_reset_required' => true,
    ]);

    grantOrganizeContentPermission($user);

    $response = $this->actingAs($user)->post(route('force-password-reset.update'), [
        'password' => 'ValidT3st!Pass99',
        'password_confirmation' => 'ValidT3st!Pass99',
    ]);

    $response->assertRedirect(route('dashboard'));
});

test('session is regenerated after password reset', function () {
    $user = User::factory()->create([
        'password' => Hash::make('OldT3st!Pass99'),
        'password_reset_required' => true,
    ]);

    // Store the initial session ID
    $this->actingAs($user);
    $initialSessionId = session()->getId();

    // Reset the password
    $this->post(route('force-password-reset.update'), [
        'password' => 'ValidT3st!Pass99',
        'password_confirmation' => 'ValidT3st!Pass99',
    ]);

    // Session ID should be different after regeneration
    expect(session()->getId())->not->toBe($initialSessionId);
});

test('user can access protected routes after password reset', function () {
    $user = User::factory()->create([
        'password' => Hash::make('OldT3st!Pass99'),
        'password_reset_required' => true,
    ]);

    grantOrganizeContentPermission($user);

    $this->actingAs($user)->post(route('force-password-reset.update'), [
        'password' => 'ValidT3st!Pass99',
        'password_confirmation' => 'ValidT3st!Pass99',
    ]);

    // User should be able to access dashboard without being redirected
    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
});

test('user can logout after password reset without csrf errors', function () {
    $user = User::factory()->create([
        'password' => Hash::make('OldT3st!Pass99'),
        'password_reset_required' => true,
    ]);

    $this->actingAs($user)->post(route('force-password-reset.update'), [
        'password' => 'ValidT3st!Pass99',
        'password_confirmation' => 'ValidT3st!Pass99',
    ]);

    // User should be able to logout without 419 error
    $response = $this->post(route('logout'));

    $response->assertRedirect();
});

test('password reset requires valid password', function () {
    $user = User::factory()->create([
        'password_reset_required' => true,
    ]);

    $response = $this->actingAs($user)->post(route('force-password-reset.update'), [
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertSessionHasErrors('password');
});

test('password reset requires matching confirmation', function () {
    $user = User::factory()->create([
        'password_reset_required' => true,
    ]);

    $response = $this->actingAs($user)->post(route('force-password-reset.update'), [
        'password' => 'ValidT3st!Pass99',
        'password_confirmation' => 'MismatchP@ss123',
    ]);

    $response->assertSessionHasErrors('password');
});

test('users without password reset requirement can access dashboard', function () {
    $user = User::factory()->create([
        'password_reset_required' => false,
    ]);

    grantOrganizeContentPermission($user);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
});

test('user can make authenticated api calls after password reset', function () {
    $user = User::factory()->create([
        'password' => Hash::make('OldT3st!Pass99'),
        'password_reset_required' => true,
    ]);

    $this->actingAs($user)->post(route('force-password-reset.update'), [
        'password' => 'ValidT3st!Pass99',
        'password_confirmation' => 'ValidT3st!Pass99',
    ]);

    // User should be able to make authenticated API calls
    $response = $this->actingAs($user)->getJson('/api/user');

    $response->assertOk();
    $response->assertJson([
        'id' => $user->id,
        'email' => $user->email,
    ]);
});
