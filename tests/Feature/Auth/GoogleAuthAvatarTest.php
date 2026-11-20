<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

beforeEach(function () {
    Storage::fake('public');
});

test('first google login downloads and stores avatar', function () {
    // Create a test user without Google ID
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'google_id' => null,
        'avatar' => null,
        'status' => 'active',
    ]);

    // Create a fake 100x100 red image
    $fakeImage = imagecreatetruecolor(100, 100);
    $red = imagecolorallocate($fakeImage, 255, 0, 0);
    imagefill($fakeImage, 0, 0, $red);
    ob_start();
    imagejpeg($fakeImage, null, 100);
    $fakeImageData = ob_get_clean();
    imagedestroy($fakeImage);

    // Mock HTTP response for avatar download
    Http::fake([
        'https://lh3.googleusercontent.com/*' => Http::response($fakeImageData, 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    // Mock Socialite Google user with simple object
    $socialiteUser = (object) [
        'id' => 'google123',
        'email' => 'test@example.com',
        'name' => 'Test User',
        'avatar' => 'https://lh3.googleusercontent.com/avatar.jpg',
    ];

    $provider = \Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($provider);

    // Make the callback request
    $response = $this->get('/auth/google/callback');

    // Assert redirect to home
    $response->assertRedirect(config('fortify.home'));

    // Refresh user from database
    $user->refresh();

    // Assert Google ID is set
    expect($user->google_id)->toBe('google123');

    // Assert avatar path is stored (not URL)
    expect($user->avatar)->not->toBeNull()
        ->and($user->avatar)->toStartWith('avatars/')
        ->and($user->avatar)->toEndWith('.jpg');

    // Assert avatar file exists in storage
    Storage::disk('public')->assertExists($user->avatar);

    // Assert image was resized to 512x512
    $storedImageData = Storage::disk('public')->get($user->avatar);
    $storedImage = imagecreatefromstring($storedImageData);
    expect(imagesx($storedImage))->toBe(512)
        ->and(imagesy($storedImage))->toBe(512);
    imagedestroy($storedImage);
});

test('google login continues without avatar when download fails', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'google_id' => null,
        'avatar' => null,
        'status' => 'active',
    ]);

    // Mock HTTP failure
    Http::fake([
        'https://lh3.googleusercontent.com/*' => Http::response(null, 404),
    ]);

    $socialiteUser = (object) [
        'id' => 'google123',
        'email' => 'test@example.com',
        'name' => 'Test User',
        'avatar' => 'https://lh3.googleusercontent.com/invalid.jpg',
    ];

    $provider = \Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn($provider);

    $response = $this->get('/auth/google/callback');

    // Should still redirect successfully
    $response->assertRedirect(config('fortify.home'));

    $user->refresh();

    // Google ID should be set
    expect($user->google_id)->toBe('google123');

    // Avatar should be null (failed download)
    expect($user->avatar)->toBeNull();

    // No avatar file should exist
    Storage::disk('public')->assertMissing('avatars/'.$user->id.'_*');
});

test('google login continues without avatar when image is invalid', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'google_id' => null,
        'avatar' => null,
        'status' => 'active',
    ]);

    // Mock HTTP response with invalid image data
    Http::fake([
        'https://lh3.googleusercontent.com/*' => Http::response('not-an-image', 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    $socialiteUser = (object) [
        'id' => 'google123',
        'email' => 'test@example.com',
        'name' => 'Test User',
        'avatar' => 'https://lh3.googleusercontent.com/corrupt.jpg',
    ];

    $provider = \Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn($provider);

    $response = $this->get('/auth/google/callback');

    // Should still redirect successfully
    $response->assertRedirect(config('fortify.home'));

    $user->refresh();

    // Google ID should be set
    expect($user->google_id)->toBe('google123');

    // Avatar should be null (invalid image)
    expect($user->avatar)->toBeNull();
});

test('google login continues without avatar when image is too large', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'google_id' => null,
        'avatar' => null,
        'status' => 'active',
    ]);

    // Create a fake image larger than 5MB
    $largeImageData = str_repeat('x', 6 * 1024 * 1024);

    Http::fake([
        'https://lh3.googleusercontent.com/*' => Http::response($largeImageData, 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    $socialiteUser = (object) [
        'id' => 'google123',
        'email' => 'test@example.com',
        'name' => 'Test User',
        'avatar' => 'https://lh3.googleusercontent.com/large.jpg',
    ];

    $provider = \Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn($provider);

    $response = $this->get('/auth/google/callback');

    // Should still redirect successfully
    $response->assertRedirect(config('fortify.home'));

    $user->refresh();

    // Google ID should be set
    expect($user->google_id)->toBe('google123');

    // Avatar should be null (too large)
    expect($user->avatar)->toBeNull();
});

test('second google login does not update avatar', function () {
    // Create a test 50x50 blue image
    $fakeImage = imagecreatetruecolor(50, 50);
    $blue = imagecolorallocate($fakeImage, 0, 0, 255);
    imagefill($fakeImage, 0, 0, $blue);
    ob_start();
    imagejpeg($fakeImage, null, 100);
    $fakeImageData = ob_get_clean();
    imagedestroy($fakeImage);

    Http::fake([
        'https://lh3.googleusercontent.com/*' => Http::response($fakeImageData, 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    // User already has Google ID (previous login)
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'google_id' => 'google123',
        'avatar' => 'avatars/existing.jpg',
        'status' => 'active',
    ]);

    // Create a fake existing avatar file
    Storage::disk('public')->put('avatars/existing.jpg', $fakeImageData);

    $socialiteUser = (object) [
        'id' => 'google123',
        'email' => 'test@example.com',
        'name' => 'Test User',
        'avatar' => 'https://lh3.googleusercontent.com/new-avatar.jpg',
    ];

    $provider = \Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn($provider);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(config('fortify.home'));

    $user->refresh();

    // Avatar should remain unchanged
    expect($user->avatar)->toBe('avatars/existing.jpg');

    // Only the existing avatar file should be present
    Storage::disk('public')->assertExists('avatars/existing.jpg');
});

test('google login rejects non-image content', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'google_id' => null,
        'avatar' => null,
        'status' => 'active',
    ]);

    // Mock HTTP response with non-image content type
    Http::fake([
        'https://lh3.googleusercontent.com/*' => Http::response('some text', 200, [
            'Content-Type' => 'text/html',
        ]),
    ]);

    $socialiteUser = (object) [
        'id' => 'google123',
        'email' => 'test@example.com',
        'name' => 'Test User',
        'avatar' => 'https://lh3.googleusercontent.com/notimage.html',
    ];

    $provider = \Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn($provider);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(config('fortify.home'));

    $user->refresh();

    expect($user->google_id)->toBe('google123')
        ->and($user->avatar)->toBeNull();
});
