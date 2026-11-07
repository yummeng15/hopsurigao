
<?php

use App\Http\Controllers\Admin\ArchivedBookingController;
use App\Http\Controllers\Admin\BoatMaintenanceController;
use App\Http\Controllers\Admin\BookingCaseController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\BookingDetailsController;
use App\Http\Controllers\Admin\BookingFeedbackController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PickupLocationController;
use App\Http\Controllers\Admin\QrscannerController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WeatherAdvisoryController;
use Illuminate\Support\Facades\Route;

// This file remains inside the authenticated, legacy-protected route group
// from routes/web.php. The shared routes retain their existing names.
Route::middleware('role:Admin,Superadmin')
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('dashboard', function () {
            return request()->user()?->role?->name === 'Superadmin'
                ? redirect()->route('superadmin.dashboard')
                : redirect()->route('operator.dashboard');
        })->name('dashboard');

        Route::middleware('role:Superadmin')->group(function (): void {
            Route::get('users/data', [UserController::class, 'getData'])->name('users.data');
            Route::resource('users', UserController::class);

            Route::get('roles/data', [RoleController::class, 'getData'])->name('roles.data');
            Route::resource('roles', RoleController::class);

            Route::get('permissions/data', [PermissionController::class, 'getData'])->name('permissions.data');
            Route::resource('permissions', PermissionController::class);
        });

        Route::get('packages/data', [PackageController::class, 'getData'])->name('packages.data');
        Route::resource('packages', PackageController::class);

        Route::get('pickup-locations/data', [PickupLocationController::class, 'getData'])->name('pickup-locations.data');
        Route::resource('pickup-locations', PickupLocationController::class);

        // Booking oversight: retain every existing route name and URL.
        Route::get('bookings/data', [BookingController::class, 'getData'])->name('bookings.data');
        Route::get('bookings/export', [BookingController::class, 'export'])->name('bookings.export');
        Route::get('bookings/calendar', [BookingController::class, 'calendar'])->name('bookings.calendar');
        Route::post('bookings/{booking}/confirm-payment', [BookingController::class, 'confirmPayment'])->name('bookings.confirm-payment');
        Route::post('bookings/{booking}/balance', [BookingController::class, 'recordBalance'])->name('bookings.balance');
        Route::get('bookings/onsite-dates/{package}', [BookingController::class, 'onsiteDates'])->name('bookings.onsite-dates');

        // NEW: separate read-only controller, same admin.bookings.show route.
        Route::get('bookings/{booking}', BookingDetailsController::class)->name('bookings.show');
        Route::resource('bookings', BookingController::class)->except(['show']);

        Route::get('archived-bookings/data', [ArchivedBookingController::class, 'getData'])->name('archived-bookings.data');
        Route::get('archived-bookings', [ArchivedBookingController::class, 'index'])->name('archived-bookings.index');
        Route::delete('archived-bookings/{booking}', [ArchivedBookingController::class, 'destroy'])->name('archived-bookings.destroy');
        Route::post('archived-bookings/bulk-delete', [ArchivedBookingController::class, 'bulkDestroy'])->name('archived-bookings.bulk-destroy');

        Route::get('qrscanner', [QrscannerController::class, 'index'])->name('qrscanner');
        Route::post('qrscanner/verify', [QrscannerController::class, 'verify'])->name('qrscanner.verify');
        Route::get('qrscanner/{booking}', [QrscannerController::class, 'show'])->name('qrscanner.show');

        Route::get('payments/data', [PaymentController::class, 'getData'])->name('payments.data');
        Route::get('payments/export', [PaymentController::class, 'export'])->name('payments.export');
        Route::resource('payments', PaymentController::class);

        Route::get('refunds/data', [RefundController::class, 'data'])->name('refunds.data');
        Route::get('refunds', [RefundController::class, 'index'])->name('refunds.index');
        Route::post('refunds/{payment}', [RefundController::class, 'store'])->name('refunds.store');
        Route::patch('booking-cases/{bookingCase}', [BookingCaseController::class, 'update'])->name('booking-cases.update');

        Route::get('weather-advisories/data', [WeatherAdvisoryController::class, 'getData'])->name('weather-advisories.data');
        Route::post('weather-advisories/{tourDate}/refresh', [WeatherAdvisoryController::class, 'refresh'])->name('weather-advisories.refresh');
        Route::post('weather-advisories/{tourDate}/travel', [WeatherAdvisoryController::class, 'toggleTravel'])->name('weather-advisories.travel');
        Route::post('weather-advisories/refresh-all', [WeatherAdvisoryController::class, 'refreshAll'])->name('weather-advisories.refresh-all');
        Route::get('weather-advisories', [WeatherAdvisoryController::class, 'index'])->name('weather-advisories.index');

        Route::get('booking-feedback', [BookingFeedbackController::class, 'index'])->name('booking-feedback.index');
        Route::get('boat-maintenance', [BoatMaintenanceController::class, 'index'])->name('boat-maintenance.index');
        Route::put('boat-maintenance/{package}', [BoatMaintenanceController::class, 'update'])->name('boat-maintenance.update');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/preview', [ReportController::class, 'preview'])->name('reports.preview');
        Route::get('reports/download', [ReportController::class, 'download'])->name('reports.download');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    });
