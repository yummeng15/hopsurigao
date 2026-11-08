<?php

use App\Http\Controllers\Auth\OperatorRegistrationController;
use App\Http\Controllers\Auth\PasswordResetCodeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PayMongoWebhookController;
use App\Http\Middleware\ProtectLegacyAdminRoutes;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Temporary one-shot (gated by Railway env). Remove after covers are assigned.
Route::get('/__assign-licensed-covers', function () {
    abort_unless(getenv('ASSIGN_LICENSED_COVERS_ONCE') === '1', 404);

    $operatorId = (int) (getenv('DEMO_TOUR_OPERATOR_ID') ?: 1);
    $exit = Artisan::call('demo:assign-licensed-covers', [
        '--operator' => $operatorId,
        '--force' => true,
    ]);

    $flag = storage_path('app/licensed-covers-assigned.flag');
    file_put_contents($flag, now()->toIso8601String()."\n".Artisan::output());

    return response(
        "exit={$exit}\noperator={$operatorId}\n\n".Artisan::output(),
        200,
        ['Content-Type' => 'text/plain; charset=UTF-8']
    );
});


Route::view('/privacy-policy', 'privacy-policy')
    ->name('privacy-policy');

    
Route::view('/terms-of-service', 'terms-of-service')
    ->name('terms-of-service');


Route::middleware('guest')->group(function () {
    Route::get('register/operator', [OperatorRegistrationController::class, 'create'])->name('register.operator');
    Route::post('register/operator', [OperatorRegistrationController::class, 'store'])->name('register.operator.store');

    Route::get('forgot-password', [PasswordResetCodeController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [PasswordResetCodeController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('forgot-password/verify', [PasswordResetCodeController::class, 'showVerify'])
        ->name('password.verify');
    Route::post('forgot-password/verify', [PasswordResetCodeController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('password.verify.store');
    Route::get('reset-password', [PasswordResetCodeController::class, 'showReset'])
        ->name('password.reset');
    Route::post('reset-password', [PasswordResetCodeController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('password.update');
});



// Public endpoint: the webhook controller must validate PayMongo signatures.
Route::post('paymongo/webhook', PayMongoWebhookController::class)->name('paymongo.webhook');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return match (auth()->user()?->role?->name) {
            'Superadmin' => redirect()->route('superadmin.dashboard'),
            'Admin' => redirect()->route(
                auth()->user()?->tourOperator?->canManageRecords()
                    ? 'operator.dashboard'
                    : 'operator.business.edit'
            ),
            'User' => redirect()->route('user.dashboard'),
            default => abort(403, 'Your account does not have an assigned portal.'),
        };
    })->name('dashboard');

    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::get('notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::patch('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    Route::delete('notifications/clear-all', [NotificationController::class, 'clearAll'])->name('notifications.clear-all');
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Each dedicated portal keeps its existing role middleware.
    require __DIR__.'/superadmin.php';
    require __DIR__.'/operator.php';

    // Transitional protection for the existing shared /admin/* controllers.
    // We preserve all current route names and URLs to avoid breaking pages.
    Route::middleware(ProtectLegacyAdminRoutes::class)->group(function () {
        require __DIR__.'/admin.php';
    });

    Route::middleware('role:User')->group(function () {
        require __DIR__.'/user.php';
    });
});

require __DIR__.'/settings.php';

require __DIR__.'/superadmin_finance.php';
