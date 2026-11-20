<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            // Find existing user by email
            $user = User::where('email', $googleUser->email)->first();

            if ($user) {
                // Check if user is active
                if ($user->status !== 'active') {
                    return redirect()->route('login')->with('error', 'Your account is inactive. Please contact your administrator.');
                }

                // Update existing user with Google ID and avatar if not set
                if (! $user->google_id) {
                    $avatarPath = $this->downloadAndStoreGoogleAvatar($googleUser->avatar, $user->id);

                    $user->update([
                        'google_id' => $googleUser->id,
                        'avatar' => $avatarPath,
                    ]);

                    $this->audit->log(
                        'google_oauth_account_linked',
                        $user->name.' linked their Google account.',
                        $user,
                        [
                            'google_email' => $googleUser->email,
                            'avatar_imported' => $avatarPath !== null,
                        ]
                    );
                }

                Auth::login($user, remember: true);

                return redirect()->intended(config('fortify.home'));
            } else {
                // Reject new users - only existing users can login via Google
                return redirect()->route('login')->with('error', 'No account found with that email address. Please contact your administrator.');
            }
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Failed to authenticate with Google. Please try again.');
        }
    }

    /**
     * Download and store Google profile avatar.
     *
     * @param  string  $avatarUrl  The Google avatar URL
     * @param  string  $userId  The user ID for unique filename
     * @return string|null The stored avatar path or null on failure
     */
    private function downloadAndStoreGoogleAvatar(string $avatarUrl, string $userId): ?string
    {
        try {
            // Download avatar from Google with timeout
            $response = Http::timeout(10)->get($avatarUrl);

            // Validate response
            if (! $response->successful()) {
                return null;
            }

            $contentType = $response->header('Content-Type');
            if (! str_starts_with($contentType, 'image/')) {
                return null;
            }

            // Check file size (max 5MB)
            $imageData = $response->body();
            if (strlen($imageData) > 5 * 1024 * 1024) {
                return null;
            }

            // Create image resource from downloaded data
            $sourceImage = @imagecreatefromstring($imageData);
            if ($sourceImage === false) {
                return null;
            }

            // Create 512x512 canvas with white background
            $targetSize = 512;
            $targetImage = imagecreatetruecolor($targetSize, $targetSize);
            $white = imagecolorallocate($targetImage, 255, 255, 255);
            imagefill($targetImage, 0, 0, $white);

            // Resize and center the image
            $sourceWidth = imagesx($sourceImage);
            $sourceHeight = imagesy($sourceImage);
            imagecopyresampled(
                $targetImage,
                $sourceImage,
                0,
                0,
                0,
                0,
                $targetSize,
                $targetSize,
                $sourceWidth,
                $sourceHeight
            );

            // Convert to JPEG with 90% quality
            ob_start();
            imagejpeg($targetImage, null, 90);
            $resizedImageData = ob_get_clean();

            // Clean up
            imagedestroy($sourceImage);
            imagedestroy($targetImage);

            // Generate unique filename and store
            $filename = 'avatars/'.$userId.'_'.time().'.jpg';
            Storage::disk('public')->put($filename, $resizedImageData);

            return $filename;
        } catch (\Exception $e) {
            // Silently fail - avatar is optional
            return null;
        }
    }
}
