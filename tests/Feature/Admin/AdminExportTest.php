<?php

use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PickupLocation;
use App\Models\PickupSchedule;
use App\Models\Role;
use App\Models\TourDate;
use App\Models\TourOperator;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

test('a superadmin can download bookings payments and the filtered report', function () {
    $superadminRole = Role::query()->firstOrCreate(['name' => 'Superadmin']);
    $touristRole = Role::query()->firstOrCreate(['name' => 'User']);

    $tourOperator = TourOperator::query()->create([
        'name' => 'Export Operator',
        'slug' => 'export-operator-'.Str::random(6),
        'is_active' => true,
    ]);

    $superadmin = User::factory()->create([
        'role_id' => $superadminRole->id,
    ]);

    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
        'name' => 'Export Guest',
    ]);

    $package = Package::query()->create([
        'tour_operator_id' => $tourOperator->id,
        'package_name' => 'Export Island Hop',
        'boat_name' => 'Export Boat',
        'destination' => 'Siargao',
        'price' => 2200,
        'status' => 'active',
        'under_maintenance' => false,
    ]);

    $tourDate = TourDate::query()->create([
        'package_id' => $package->id,
        'tour_date' => today()->addDays(5)->toDateString(),
        'capacity' => 20,
        'travel_closed' => false,
    ]);

    $pickupLocation = PickupLocation::query()->create([
        'tour_operator_id' => $tourOperator->id,
        'name' => 'Export Pier '.Str::random(4),
        'latitude' => 9.86,
        'longitude' => 126.08,
        'status' => 'active',
    ]);

    $schedule = PickupSchedule::query()->create([
        'tour_date_id' => $tourDate->id,
        'pickup_location_id' => $pickupLocation->id,
        'pickup_time' => '07:00:00',
    ]);

    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $tourDate->id,
        'pickup_schedule_id' => $schedule->id,
        'number_of_guests' => 2,
        'phone_number' => '09176661017',
        'nationality' => 'Filipino',
        'booking_status' => 'confirmed',
    ]);

    Payment::query()->create([
        'booking_id' => $booking->id,
        'amount' => 4400,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'notes' => 'Cash collected on site.',
    ]);

    $tourDateString = $tourDate->tour_date->toDateString();

    $bookings = $this->actingAs($superadmin)->get(route('admin.bookings.export'));
    $bookings->assertOk();
    expect($bookings->streamedContent())->toContain('Export Guest')
        ->and($bookings->headers->get('content-disposition'))->toContain('bookings-');

    $payments = $this->actingAs($superadmin)->get(route('admin.payments.export'));
    $payments->assertOk();
    expect($payments->streamedContent())->toContain('4400')
        ->and($payments->headers->get('content-disposition'))->toContain('payments-');

    $report = $this->actingAs($superadmin)->get(route('admin.reports.export', [
        'type' => 'booking',
        'date_from' => $tourDateString,
        'date_to' => $tourDateString,
    ]));
    $report->assertOk();
    expect($report->streamedContent())->toContain('Export Guest')
        ->and($report->headers->get('content-disposition'))->toContain('booking-report-');
});

test('a tourist cannot download admin exports', function () {
    $touristRole = Role::query()->firstOrCreate(['name' => 'User']);

    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
    ]);

    $this->actingAs($tourist)->get(route('admin.bookings.export'))->assertForbidden();
    $this->actingAs($tourist)->get(route('admin.payments.export'))->assertForbidden();
    $this->actingAs($tourist)->get(route('admin.reports.export', [
        'type' => 'booking',
    ]))->assertForbidden();
});
