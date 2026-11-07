
<?php

use App\Http\Controllers\Admin\OperatorDashboardController;
use App\Http\Controllers\Operator\BusinessController;
use App\Http\Controllers\Operator\OperatorBookingController;
use App\Http\Controllers\Operator\OperatorOperationsController;
use App\Http\Controllers\Operator\OperatorPackagePublicationController;
use App\Http\Controllers\Operator\OperatorPackagesController;
use App\Http\Controllers\Operator\OperatorPackageWriteController;
use App\Http\Controllers\Operator\OperatorPaymentController;
use App\Http\Controllers\Operator\OperatorQrController;
use App\Http\Controllers\Operator\OperatorRefundController;
use App\Http\Controllers\Operator\OperatorScheduleController;
use App\Http\Controllers\Operator\OperatorTripController;
use App\Http\Controllers\Operator\OperatorWalkInController;
use App\Http\Controllers\Operator\SettingsController;
use Illuminate\Support\Facades\Route;

// Included inside auth + verified middleware in routes/web.php.
Route::middleware('role:Admin')
    ->prefix('operator')
    ->name('operator.')
    ->group(function () {

        Route::get(
            'dashboard',
            [OperatorDashboardController::class, 'index']
        )->name('dashboard');

        Route::get('business', [BusinessController::class, 'edit'])->name('business.edit');
        Route::post('business', [BusinessController::class, 'store'])->name('business.store');
        Route::patch('business', [BusinessController::class, 'update'])->name('business.update');
        Route::post('business/package-requests', [BusinessController::class, 'requestPackage'])->name('business.package-requests.store');

        // Package management.
        Route::get(
            'packages',
            OperatorPackagesController::class
        )->name('packages.index');

        Route::get(
            'packages/create',
            [OperatorPackageWriteController::class, 'create']
        )->name('packages.create');

        Route::post(
            'packages',
            [OperatorPackageWriteController::class, 'store']
        )->name('packages.store');

        Route::get(
            'packages/{package}/edit',
            [OperatorPackageWriteController::class, 'edit']
        )->whereNumber('package')->name('packages.edit');

        Route::put(
            'packages/{package}',
            [OperatorPackageWriteController::class, 'update']
        )->whereNumber('package')->name('packages.update');

        Route::post(
            'packages/{package}/publish',
            [OperatorPackagePublicationController::class, 'publish']
        )->whereNumber('package')->name('packages.publish');

        Route::post(
            'packages/{package}/unpublish',
            [OperatorPackagePublicationController::class, 'unpublish']
        )->whereNumber('package')->name('packages.unpublish');

        // Bookings.
        Route::get(
            'bookings',
            [OperatorBookingController::class, 'index']
        )->name('bookings.index');

        Route::get(
            'bookings/calendar',
            [OperatorBookingController::class, 'calendar']
        )->name('bookings.calendar');

        Route::get(
            'bookings/{booking}',
            [OperatorBookingController::class, 'show']
        )->whereNumber('booking')->name('bookings.show');

        // Attendance and trip outcomes (owned bookings only).
        Route::get(
            'bookings/{booking}/trip',
            [OperatorTripController::class, 'show']
        )->whereNumber('booking')->name('trips.show');

        Route::post(
            'bookings/{booking}/trip/check-in',
            [OperatorTripController::class, 'checkIn']
        )->whereNumber('booking')->name('trips.check-in');

        Route::post(
            'bookings/{booking}/trip/outcome',
            [OperatorTripController::class, 'outcome']
        )->whereNumber('booking')->name('trips.outcome');

        // Cash-only walk-in reservations.
        Route::get(
            'walk-ins/create',
            [OperatorWalkInController::class, 'create']
        )->name('walk-ins.create');

        Route::get(
            'walk-ins/packages/{package}/dates',
            [OperatorWalkInController::class, 'dates']
        )->whereNumber('package')->name('walk-ins.dates');

        Route::post(
            'walk-ins',
            [OperatorWalkInController::class, 'store']
        )->name('walk-ins.store');

        // QR verification.
        Route::get(
            'qr-scanner',
            [OperatorQrController::class, 'index']
        )->name('qr.index');

        Route::post(
            'qr-scanner/verify',
            [OperatorQrController::class, 'verify']
        )->name('qr.verify');

        Route::get(
            'qr-scanner/bookings/{booking}',
            [OperatorQrController::class, 'show']
        )->whereNumber('booking')->name('qr.show');

        // Payments.
        Route::get(
            'payments',
            [OperatorPaymentController::class, 'index']
        )->name('payments.index');

        Route::get(
            'payments/{booking}',
            [OperatorPaymentController::class, 'show']
        )->whereNumber('booking')->name('payments.show');

        Route::post(
            'payments/{booking}/collect-balance',
            [OperatorPaymentController::class, 'collect']
        )->whereNumber('booking')->name('payments.collect');

        Route::post(
            'payments/{payment}/refund',
            [OperatorPaymentController::class, 'refund']
        )->whereNumber('payment')->name('payments.refund');

        Route::get('refunds', [OperatorRefundController::class, 'index'])
            ->name('refunds.index');
        Route::get('refunds/{refund}', [OperatorRefundController::class, 'show'])
            ->whereNumber('refund')
            ->name('refunds.show');
        Route::post('refunds/{refund}/approve', [OperatorRefundController::class, 'approve'])
            ->whereNumber('refund')
            ->name('refunds.approve');
        Route::post('refunds/{refund}/reject', [OperatorRefundController::class, 'reject'])
            ->whereNumber('refund')
            ->name('refunds.reject');

        // Tour schedules.
        Route::get(
            'packages/{package}/schedules',
            [OperatorScheduleController::class, 'index']
        )->whereNumber('package')->name('schedules.index');

        Route::post(
            'packages/{package}/schedules',
            [OperatorScheduleController::class, 'store']
        )->whereNumber('package')->name('schedules.store');

        Route::put(
            'packages/{package}/schedules/{tourDate}',
            [OperatorScheduleController::class, 'update']
        )
            ->whereNumber('package')
            ->whereNumber('tourDate')
            ->name('schedules.update');

        // Complete Operator Operations.
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
        Route::put('settings/password', [SettingsController::class, 'updatePassword'])
            ->middleware('throttle:6,1')
            ->name('settings.password');
        Route::patch('settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications');

        Route::get(
            'operations',
            [OperatorOperationsController::class, 'index']
        )->name('operations.index');

        Route::get(
            'operations/boats',
            [OperatorOperationsController::class, 'boats']
        )->name('operations.boats');

        Route::get(
            'operations/pickups',
            [OperatorOperationsController::class, 'pickups']
        )->name('operations.pickups');

        Route::get(
            'operations/weather',
            [OperatorOperationsController::class, 'weather']
        )->name('operations.weather');

        Route::get(
            'operations/feedback',
            [OperatorOperationsController::class, 'feedback']
        )->name('operations.feedback');

        Route::get(
            'operations/reports',
            [OperatorOperationsController::class, 'reports']
        )->name('operations.reports');

        Route::patch(
            'operations/boats/{package}',
            [OperatorOperationsController::class, 'updateBoat']
        )->whereNumber('package')->name('operations.boats.update');

        Route::post(
            'operations/pickup-locations',
            [OperatorOperationsController::class, 'storeLocation']
        )->name('operations.pickups.store');

        Route::patch(
            'operations/pickup-locations/{location}',
            [OperatorOperationsController::class, 'updateLocation']
        )->whereNumber('location')->name('operations.pickups.update');

        Route::delete(
            'operations/pickup-locations/{location}',
            [OperatorOperationsController::class, 'destroyLocation']
        )->whereNumber('location')->name('operations.pickups.destroy');

        Route::post(
            'operations/weather/{tourDate}/toggle',
            [OperatorOperationsController::class, 'toggleTravel']
        )->whereNumber('tourDate')->name('operations.weather.toggle');

        Route::post(
            'operations/weather/{tourDate}/refresh',
            [OperatorOperationsController::class, 'refreshWeather']
        )->whereNumber('tourDate')->name('operations.weather.refresh');

        Route::get(
            'operations/report.csv',
            [OperatorOperationsController::class, 'report']
        )->name('operations.report');
    });
