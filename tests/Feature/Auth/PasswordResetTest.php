<?php

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'MyT3st!P@ssword',
            'password_confirmation' => 'MyT3st!P@ssword',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'Secure#Test2024',
        'password_confirmation' => 'Secure#Test2024',
    ]);

    $response->assertSessionHasErrors('email');
});

test('session is regenerated after password reset', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        // Store the initial session ID
        $initialSessionId = session()->getId();

        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Secure#Test2024',
            'password_confirmation' => 'Secure#Test2024',
        ]);

        // Session ID should be different after regeneration
        expect(session()->getId())->not->toBe($initialSessionId);

        return true;
    });
});

test('user is authenticated after password reset', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Secure#Test2024',
            'password_confirmation' => 'Secure#Test2024',
        ]);

        // User should be authenticated after password reset
        expect($this->isAuthenticated())->toBeTrue();
        expect(auth()->id())->toBe($user->id);

        return true;
    });
});

test('password_reset_required flag is cleared after password reset', function () {
    Notification::fake();

    $user = User::factory()->create([
        'password_reset_required' => true,
    ]);

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Secure#Test2024',
            'password_confirmation' => 'Secure#Test2024',
        ]);

        $user->refresh();
        expect($user->password_reset_required)->toBeFalse();

        return true;
    });
});
