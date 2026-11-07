<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Show the signed-in Super Admin's settings.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('superadmin/Settings', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'preferences' => $this->preferences($user),
            'about' => $this->about(),
            'profileStatus' => $request->session()->get('profile_status'),
            'passwordStatus' => $request->session()->get('password_status'),
            'notificationStatus' => $request->session()->get('notification_status'),
        ]);
    }

    /**
     * Update the Super Admin's name and email.
     */
    public function updateProfile(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return to_route('superadmin.settings.edit')->with('profile_status', 'Profile saved.');
    }

    /**
     * Change the Super Admin's password after confirming the current one.
     */
    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        return to_route('superadmin.settings.edit')->with('password_status', 'Password updated.');
    }

    /**
     * Save optional in-app notification categories for this account.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bookings' => ['required', 'boolean'],
            'cancellations' => ['required', 'boolean'],
            'payments' => ['required', 'boolean'],
            'weather' => ['required', 'boolean'],
        ]);

        $request->user()->forceFill([
            'notification_preferences' => [
                'bookings' => (bool) $validated['bookings'],
                'cancellations' => (bool) $validated['cancellations'],
                'payments' => (bool) $validated['payments'],
                'weather' => (bool) $validated['weather'],
            ],
        ])->save();

        return to_route('superadmin.settings.edit')->with('notification_status', 'Notification preferences saved.');
    }

    /**
     * @return array{bookings: bool, cancellations: bool, payments: bool, weather: bool}
     */
    private function preferences(User $user): array
    {
        $saved = is_array($user->notification_preferences) ? $user->notification_preferences : [];

        return [
            'bookings' => (bool) ($saved['bookings'] ?? true),
            'cancellations' => (bool) ($saved['cancellations'] ?? true),
            'payments' => (bool) ($saved['payments'] ?? true),
            'weather' => (bool) ($saved['weather'] ?? true),
        ];
    }

    /**
     * Public application details. Secrets and API keys are never included.
     *
     * @return array{name: string, version: string, framework: string, php: string}
     */
    private function about(): array
    {
        $version = 'Not set';
        $composerPath = base_path('composer.json');

        if (File::exists($composerPath)) {
            $composer = json_decode((string) File::get($composerPath), true);
            if (is_array($composer) && is_string($composer['version'] ?? null) && $composer['version'] !== '') {
                $version = $composer['version'];
            }
        }

        return [
            'name' => (string) config('app.name'),
            'version' => $version,
            'framework' => 'Laravel '.app()->version(),
            'php' => PHP_VERSION,
        ];
    }
}
