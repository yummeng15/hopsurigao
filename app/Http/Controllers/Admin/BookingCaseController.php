<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingCaseRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingCaseController extends Controller
{
    public function update(Request $request, BookingCaseRequest $bookingCase): RedirectResponse
    {
        /*
         * Refund decisions belong to the Operator Admin.
         * Super Admin refund oversight is view-only.
         */
        abort(
            403,
            'Refund and incident decisions are handled by the Operator Admin. Super Admin oversight is view only.'
        );
    }
}
