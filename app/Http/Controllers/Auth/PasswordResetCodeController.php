<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordResetCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetCodeController extends Controller
{
    use PasswordValidationRules;

    public function __construct(
        private PasswordResetCodeService $codes,
    ) {}

    public function create(Request $request): Response
    {
        return Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $this->codes->send($validated['email']);

        $request->session()->put(
            'password_reset_email',
            strtolower(trim($validated['email']))
        );
        $request->session()->forget('password_reset_verified');

        return redirect()
            ->route('password.verify')
            ->with(
                'status',
                'If that email is registered, a 6-digit code was sent. Check your inbox and spam folder.'
            );
    }

    public function showVerify(Request $request): Response|RedirectResponse
    {
        $email = $request->session()->get('password_reset_email');

        if (! is_string($email) || $email === '') {
            return redirect()->route('password.request');
        }

        return Inertia::render('auth/VerifyPasswordResetCode', [
            'email' => $email,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $email = $request->session()->get('password_reset_email');

        if (! is_string($email) || $email === '') {
            return redirect()->route('password.request');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        $this->codes->verify($email, $validated['code']);

        $request->session()->put('password_reset_verified', true);

        return redirect()->route('password.reset');
    }

    public function showReset(Request $request): Response|RedirectResponse
    {
        $email = $request->session()->get('password_reset_email');
        $verified = (bool) $request->session()->get('password_reset_verified');

        if (! is_string($email) || $email === '' || ! $verified || ! $this->codes->hasVerifiedCode($email)) {
            return redirect()->route('password.request');
        }

        return Inertia::render('auth/ResetPassword', [
            'email' => $email,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $email = $request->session()->get('password_reset_email');
        $verified = (bool) $request->session()->get('password_reset_verified');

        if (! is_string($email) || $email === '' || ! $verified || ! $this->codes->hasVerifiedCode($email)) {
            throw ValidationException::withMessages([
                'email' => 'Verify your 6-digit code before setting a new password.',
            ]);
        }

        $validated = $request->validate([
            'password' => $this->passwordRules(),
        ]);

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'We could not reset this password. Request a new code.',
            ]);
        }

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        $this->codes->clear($email);
        $request->session()->forget([
            'password_reset_email',
            'password_reset_verified',
        ]);

        return redirect()
            ->route('login')
            ->with('status', 'Your password was updated. You can log in now.');
    }
}
