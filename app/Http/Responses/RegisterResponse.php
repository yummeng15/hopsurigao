<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request)
    {
        Auth::guard('web')->logout();

        $status = 'Your account was created. Please log in.';

        return $request->wantsJson()
            ? response()->json(['status' => $status])
            : redirect()->route('login')->with('status', $status);
    }
}
