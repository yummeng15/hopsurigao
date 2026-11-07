
<?php

use App\Http\Controllers\User\BookingCancellationController;
use App\Http\Controllers\User\BookingCaseController;
use App\Http\Controllers\User\BookingController;
use App\Http\Controllers\User\BookingCreationController;
use App\Http\Controllers\User\BookingEditController;
use App\Http\Controllers\User\BookingFeedbackController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\PackageController;
use App\Http\Controllers\User\PayMongoPaymentController;
use App\Http\Controllers\User\RefundRequestController;
use App\Http\Controllers\User\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HopSurigao Tourist Routes
|--------------------------------------------------------------------------
| The outer routes/web.php group already applies auth and verified.
| Do not remove the Tourist role check here.
*/
Route::middleware('role:User')
    ->prefix('user')
    ->name('user.')
    ->group(function (): void {
        Route::get('dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
        Route::put('settings/password', [SettingsController::class, 'updatePassword'])
            ->middleware('throttle:6,1')
            ->name('settings.password');
        Route::patch('settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications');

        Route::get('packages/{package}/images', [PackageController::class, 'images'])
            ->name('packages.images');

        Route::resource('packages', PackageController::class)
            ->only(['index', 'show']);

        /*
         * New booking POST is handled by BookingCreationController.
         * It rechecks weather, maintenance, pickup and seats under locks.
         */
        Route::post('bookings', BookingCreationController::class)
            ->name('bookings.store');

        /*
         * Pending booking edits cannot change guests, date or saved prices.
         */
        Route::get('bookings/{booking}/edit', [BookingEditController::class, 'edit'])
            ->name('bookings.edit');

        Route::match(['put', 'patch'], 'bookings/{booking}', [BookingEditController::class, 'update'])
            ->name('bookings.update');

        /*
         * Existing Tourist views and creation form stay in BookingController.
         * Dedicated controllers own store, edit, update and destroy.
         */
        Route::resource('bookings', BookingController::class)
            ->except(['store', 'edit', 'update', 'destroy']);

        Route::delete('bookings/{booking}', [BookingCancellationController::class, 'destroy'])
            ->name('bookings.destroy');

        /*
         * Payment and PayMongo: preserve the verified hosted checkout flow.
         */
        Route::get('bookings/{booking}/payment', [BookingController::class, 'payment'])
            ->name('bookings.payment');

        Route::post('bookings/{booking}/payment', [BookingController::class, 'submitPayment'])
            ->name('bookings.payment.submit');

        Route::post('bookings/{booking}/payment/checkout', [PayMongoPaymentController::class, 'checkout'])
            ->name('bookings.payment.checkout');

        Route::get('bookings/{booking}/payment/return', [PayMongoPaymentController::class, 'returned'])
            ->name('bookings.payment.return');

        /*
         * Post-tour feedback lives under Feedback in the tourist navbar.
         */
        Route::get('feedback', [BookingFeedbackController::class, 'index'])
            ->name('feedback.index');

        Route::get('bookings/{booking}/feedback', [BookingFeedbackController::class, 'create'])
            ->name('bookings.feedback.create');

        Route::post('bookings/{booking}/feedback', [BookingFeedbackController::class, 'store'])
            ->name('bookings.feedback.store');

        Route::patch('bookings/{booking}/cancel', [BookingCancellationController::class, 'cancel'])
            ->name('bookings.cancel');

        Route::post('bookings/{booking}/cases', [BookingCaseController::class, 'store'])
            ->name('bookings.cases.store');

        Route::post('bookings/{booking}/refunds', [RefundRequestController::class, 'store'])
            ->name('bookings.refunds.store');

        Route::patch('bookings/{booking}/archive', [BookingController::class, 'archive'])
            ->name('bookings.archive');

        Route::get('bookings/{booking}/qr', [BookingController::class, 'qr'])
            ->name('bookings.qr');

        /*
         * Booking history retains financial records; old deletion stays blocked.
         */
        Route::get('history', [BookingController::class, 'history'])
            ->name('history');

        Route::post('history/clear', [BookingController::class, 'clearHistory'])
            ->name('history.clear');
    });
