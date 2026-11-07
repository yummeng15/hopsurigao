<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $roleName = $request->user()?->role?->name;

        $redirectTo = match ($roleName) {
            'Superadmin' => route('superadmin.dashboard'),
            'Admin' => route('operator.dashboard'),
            'User' => route('user.dashboard'),
            default => route('dashboard'),
        };

        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->intended($redirectTo);
    }
}