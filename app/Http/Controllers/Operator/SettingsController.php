<?php

namespace App\Http\Controllers\Operator;

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
     * Show the signed-in tour operator's settings.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('operator/Settings', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'preferences' => $this->preferences($user),
            'profileStatus' => $request->session()->get('profile_status'),
            'passwordStatus' => $request->session()->get('password_status'),
            'notificationStatus' => $request->session()->get('notification_status'),
        ]);
    }

    /**
     * Update this operator account's name and email.
     */
    public function updateProfile(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return to_route('operator.settings.edit')->with('profile_status', 'Profile saved.');
    }

    /**
     * Change this operator account's password.
     */
    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        return to_route('operator.settings.edit')->with('password_status', 'Password updated.');
    }

    /**
     * Save optional booking and trip alerts for this operator account.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bookings' => ['required', 'boolean'],
            'trips' => ['required', 'boolean'],
        ]);

        $request->user()->forceFill([
            'notification_preferences' => [
                'bookings' => (bool) $validated['bookings'],
                'trips' => (bool) $validated['trips'],
            ],
        ])->save();

        return to_route('operator.settings.edit')->with('notification_status', 'Notification preferences saved.');
    }

    /**
     * @return array{bookings: bool, trips: bool}
     */
    private function preferences(User $user): array
    {
        $saved = is_array($user->notification_preferences) ? $user->notification_preferences : [];

        return [
            'bookings' => (bool) ($saved['bookings'] ?? true),
            'trips' => (bool) ($saved['trips'] ?? true),
        ];
    }
}
