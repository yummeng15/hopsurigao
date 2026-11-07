
<?php

use App\Http\Controllers\Superadmin\OperatorFinanceOverviewController;
use Illuminate\Support\Facades\Route;

// A separate route file avoids replacing the existing operator/payout routes.
Route::middleware(['auth', 'verified', 'role:Superadmin'])
    ->prefix('superadmin')
    ->name('superadmin.')
    ->group(function (): void {
        Route::get('operator-finance', OperatorFinanceOverviewController::class)
            ->name('operator-finance.index');
    });
