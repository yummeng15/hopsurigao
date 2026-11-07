<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Show the signed-in tourist's settings.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('user/Settings', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'contact_number' => $user->contact_number,
            ],
            'preferences' => $this->preferences($user),
            'profileStatus' => $request->session()->get('profile_status'),
            'passwordStatus' => $request->session()->get('password_status'),
            'notificationStatus' => $request->session()->get('notification_status'),
        ]);
    }

    /**
     * Update this tourist's name, email, and contact number.
     */
    public function updateProfile(ProfileUpdateRequest $request): RedirectResponse
    {
        $contact = $request->validate([
            'contact_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],
        ]);

        $user = $request->user();
        $user->fill([
            ...$request->validated(),
            'contact_number' => $contact['contact_number'] ?? null,
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return to_route('user.settings.edit')->with('profile_status', 'Profile saved.');
    }

    /**
     * Change this tourist's password.
     */
    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        return to_route('user.settings.edit')->with('password_status', 'Password updated.');
    }

    /**
     * Save optional alerts. Essential booking, payment, and safety alerts stay on.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cancellations' => ['required', 'boolean'],
        ]);

        $request->user()->forceFill([
            'notification_preferences' => [
                'cancellations' => (bool) $validated['cancellations'],
            ],
        ])->save();

        return to_route('user.settings.edit')->with('notification_status', 'Notification preferences saved.');
    }

    /**
     * @return array{cancellations: bool}
     */
    private function preferences(User $user): array
    {
        $saved = is_array($user->notification_preferences) ? $user->notification_preferences : [];

        return [
            'cancellations' => (bool) ($saved['cancellations'] ?? true),
        ];
    }
}
