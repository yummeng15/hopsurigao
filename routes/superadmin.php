
<?php

use App\Http\Controllers\Superadmin\DashboardController;
use App\Http\Controllers\Superadmin\DocumentationController;
use App\Http\Controllers\Superadmin\OperatorAdminController;
use App\Http\Controllers\Superadmin\SettingsController;
use App\Http\Controllers\Superadmin\TourOperatorController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:Superadmin')
    ->prefix('superadmin')
    ->name('superadmin.')
    ->group(function () {

        Route::get(
            'dashboard',
            [DashboardController::class, 'index']
        )->name('dashboard');

        Route::get(
            'documentation',
            [DocumentationController::class, 'index']
        )->name('documentation');

        Route::get(
            'settings',
            [SettingsController::class, 'edit']
        )->name('settings.edit');

        Route::patch(
            'settings/profile',
            [SettingsController::class, 'updateProfile']
        )->name('settings.profile');

        Route::put(
            'settings/password',
            [SettingsController::class, 'updatePassword']
        )->middleware('throttle:6,1')->name('settings.password');

        Route::patch(
            'settings/notifications',
            [SettingsController::class, 'updateNotifications']
        )->name('settings.notifications');

        // Tour Operator registration.
        Route::get(
            'operators',
            [TourOperatorController::class, 'index']
        )->name('operators.index');

        Route::post(
            'operators',
            [TourOperatorController::class, 'store']
        )->name('operators.store');

        Route::patch(
            'operators/{operator}/assignments',
            [TourOperatorController::class, 'assign']
        )->whereNumber('operator')->name('operators.assign');

        Route::patch(
            'package-assignments',
            [TourOperatorController::class, 'assignPackages']
        )->name('package-assignments.update');

        Route::patch(
            'operators/{operator}/decision',
            [TourOperatorController::class, 'decide']
        )->whereNumber('operator')->name('operators.decide');

        Route::patch(
            'package-ownership-requests/{ownershipRequest}',
            [TourOperatorController::class, 'reviewPackageRequest']
        )->whereNumber('ownershipRequest')->name('package-ownership-requests.review');

        // Operator Admin management.
        Route::get(
            'operator-admins',
            [OperatorAdminController::class, 'index']
        )->name('operator-admins.index');

        Route::post(
            'operator-admins',
            [OperatorAdminController::class, 'store']
        )->name('operator-admins.store');

        // Assign an existing unassigned Admin.
        Route::patch(
            'operator-admins/{admin}/assign',
            [OperatorAdminController::class, 'assign']
        )
            ->whereNumber('admin')
            ->name('operator-admins.assign');
    });
