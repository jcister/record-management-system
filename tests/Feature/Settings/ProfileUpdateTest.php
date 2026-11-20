<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can upload an avatar while updating profile', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $avatar = UploadedFile::fake()->image('avatar.jpg', 800, 800);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $avatar,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $updatedUser = $user->fresh();

    expect($updatedUser?->avatar)->not->toBeNull();
    Storage::disk('public')->assertExists($updatedUser->avatar);
});

test('uploading a new avatar removes previous avatar file', function () {
    Storage::fake('public');

    $oldAvatarPath = UploadedFile::fake()->image('old-avatar.jpg', 600, 600)
        ->store('avatars', 'public');

    $user = User::factory()->create([
        'avatar' => $oldAvatarPath,
    ]);

    $newAvatar = UploadedFile::fake()->image('new-avatar.jpg', 900, 900);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $newAvatar,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $updatedUser = $user->fresh();

    expect($updatedUser?->avatar)->not->toBe($oldAvatarPath);
    Storage::disk('public')->assertMissing($oldAvatarPath);
    Storage::disk('public')->assertExists($updatedUser->avatar);
});

test('user can delete their avatar', function () {
    Storage::fake('public');

    $avatarPath = UploadedFile::fake()->image('avatar.jpg', 700, 700)
        ->store('avatars', 'public');

    $user = User::factory()->create([
        'avatar' => $avatarPath,
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.avatar.delete'));

    $response->assertRedirect(route('profile.edit'));

    expect($user->fresh()?->avatar)->toBeNull();
    Storage::disk('public')->assertMissing($avatarPath);
});

test('avatar must be an image file', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $invalidFile = UploadedFile::fake()->create('avatar.pdf', 200, 'application/pdf');

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $invalidFile,
        ]);

    $response->assertSessionHasErrors('avatar');
});

test('avatar larger than 5mb is rejected', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $largeAvatar = UploadedFile::fake()->image('avatar.jpg', 1200, 1200)->size(5201);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $largeAvatar,
        ]);

    $response->assertSessionHasErrors('avatar');
});

test('user cannot delete their own account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'P@ssw0rd123',
        ]);

    $response->assertForbidden();
    expect($user->fresh())->not->toBeNull();
});

test('self-delete returns forbidden with valid password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'P@ssw0rd123',
        ]);

    $response->assertForbidden();
    expect($user->fresh())->not->toBeNull();
});
