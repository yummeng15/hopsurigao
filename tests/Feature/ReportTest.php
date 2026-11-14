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
use App\Models\WeatherForecast;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminRole = Role::firstOrCreate(['name' => 'Admin'], ['description' => 'Operator']);
    $this->superadminRole = Role::firstOrCreate(['name' => 'Superadmin'], ['description' => 'Superadmin']);
    $this->userRole = Role::firstOrCreate(['name' => 'User'], ['description' => 'Tourist']);

    $this->tourOperator = TourOperator::query()->create([
        'name' => 'Report Operator',
        'slug' => 'report-op-'.Str::uuid(),
        'is_active' => true,
        'approval_status' => TourOperator::STATUS_ACTIVE,
    ]);

    $this->operator = User::factory()->create([
        'role_id' => $this->adminRole->id,
        'tour_operator_id' => $this->tourOperator->id,
        'email_verified_at' => now(),
    ]);

    $this->superadmin = User::factory()->create([
        'role_id' => $this->superadminRole->id,
        'email_verified_at' => now(),
    ]);

    $this->tourist = User::factory()->create([
        'role_id' => $this->userRole->id,
        'email_verified_at' => now(),
    ]);

    $this->package = Package::create([
        'tour_operator_id' => $this->tourOperator->id,
        'package_name' => 'Siargao Island Hopping',
        'destination' => 'Siargao',
        'description' => 'Amazing tour experience',
        'price' => 1500,
        'status' => 'active',
        'latitude' => 9.8583,
        'longitude' => 126.0461,
    ]);

    $this->tourDate = TourDate::create([
        'package_id' => $this->package->id,
        'tour_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
        'capacity' => 20,
    ]);

    $this->location = PickupLocation::create([
        'tour_operator_id' => $this->tourOperator->id,
        'name' => 'General Luna Pier',
        'address' => 'General Luna, Surigao del Norte',
    ]);

    $this->pickupSchedule = PickupSchedule::create([
        'tour_date_id' => $this->tourDate->id,
        'pickup_location_id' => $this->location->id,
        'pickup_time' => '07:00:00',
    ]);

    $this->booking = Booking::create([
        'user_id' => $this->tourist->id,
        'tour_date_id' => $this->tourDate->id,
        'pickup_schedule_id' => $this->pickupSchedule->id,
        'number_of_guests' => 4,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'special_request' => 'Vegetarian lunch',
        'booking_status' => 'confirmed',
    ]);

    $this->payment = Payment::create([
        'booking_id' => $this->booking->id,
        'amount' => 6000,
        'payment_method' => 'gcash',
        'payment_status' => 'paid',
        'transaction_reference' => 'GCASH-123456',
        'paid_at' => Carbon::now(),
    ]);

    $this->forecast = WeatherForecast::create([
        'tour_date_id' => $this->tourDate->id,
        'forecast_date' => $this->tourDate->tour_date,
        'location' => 'Siargao',
        'temperature_min' => 24,
        'temperature_max' => 31,
        'weather_condition' => 'Partly Cloudy',
        'weather_code' => 1,
        'rainfall_probability' => 20,
        'wind_speed' => 12,
        'advisory_level' => 'green',
        'advisory_badge' => 'Normal',
        'advisory_message' => 'Good maritime conditions.',
    ]);
});

test('only superadmin can access platform reports index page', function () {
    $this->actingAs($this->operator)->get('/admin/reports')->assertForbidden();

    $this->actingAs($this->superadmin)->get('/admin/reports')->assertOk();
});

test('tourist cannot access administrative reports and receives 403', function () {
    $this->actingAs($this->tourist)->get('/admin/reports')->assertForbidden();
});

test('booking report preview returns expected kpi summaries and records', function () {
    $response = $this->actingAs($this->superadmin)->getJson('/admin/reports/preview?'.http_build_query([
        'type' => 'booking',
        'package_id' => 'all',
        'booking_status' => 'all',
        'pickup_location_id' => 'all',
        'nationality' => 'all',
        'date_from' => Carbon::now()->subDays(1)->format('Y-m-d'),
        'date_to' => Carbon::now()->addDays(10)->format('Y-m-d'),
    ]));

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.summary.total_bookings', 1);
    $response->assertJsonPath('data.summary.confirmed_bookings', 1);
    $response->assertJsonPath('data.summary.total_guests', 4);
    $response->assertJsonCount(1, 'data.records');
});

test('tour schedule report calculates capacities and booked guests correctly', function () {
    $response = $this->actingAs($this->superadmin)->getJson('/admin/reports/preview?'.http_build_query([
        'type' => 'tour-schedule',
        'date_from' => Carbon::now()->subDays(1)->format('Y-m-d'),
        'date_to' => Carbon::now()->addDays(10)->format('Y-m-d'),
    ]));

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.summary.total_schedules', 1);
    $response->assertJsonPath('data.summary.total_capacity', 20);
    $response->assertJsonPath('data.summary.total_booked_guests', 4);
    $response->assertJsonPath('data.summary.total_available_slots', 16);
});

test('payment revenue report calculates verified revenue and channel distributions', function () {
    $response = $this->actingAs($this->superadmin)->getJson('/admin/reports/preview?'.http_build_query([
        'type' => 'payment',
        'date_from' => Carbon::now()->subDays(1)->format('Y-m-d'),
        'date_to' => Carbon::now()->addDays(1)->format('Y-m-d'),
    ]));

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.summary.total_revenue', 6000);
    $response->assertJsonPath('data.summary.paid_confirmed_count', 1);
});

test('tourist manifest report summarizes guests and nationality counts', function () {
    $response = $this->actingAs($this->superadmin)->getJson('/admin/reports/preview?'.http_build_query([
        'type' => 'tourist',
        'date_from' => Carbon::now()->subDays(1)->format('Y-m-d'),
        'date_to' => Carbon::now()->addDays(10)->format('Y-m-d'),
    ]));

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.summary.total_lead_travelers', 1);
    $response->assertJsonPath('data.summary.total_guests', 4);
    $response->assertJsonPath('data.records.0.nationality', 'Filipino');
});

test('pickup schedule report groups passenger parties by pickup locations', function () {
    $response = $this->actingAs($this->superadmin)->getJson('/admin/reports/preview?'.http_build_query([
        'type' => 'pickup-schedule',
        'date_from' => Carbon::now()->subDays(1)->format('Y-m-d'),
        'date_to' => Carbon::now()->addDays(10)->format('Y-m-d'),
    ]));

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.summary.total_guests_to_pickup', 4);
    $response->assertJsonPath('data.records.0.location_name', 'General Luna Pier');
});

test('package performance report computes sales conversion and verified revenue', function () {
    $response = $this->actingAs($this->superadmin)->getJson('/admin/reports/preview?'.http_build_query([
        'type' => 'package-performance',
        'date_from' => Carbon::now()->subDays(1)->format('Y-m-d'),
        'date_to' => Carbon::now()->addDays(10)->format('Y-m-d'),
    ]));

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.summary.overall_total_bookings', 1);
    $response->assertJsonPath('data.summary.overall_total_revenue', 6000);
    $response->assertJsonPath('data.records.0.package_name', 'Siargao Island Hopping');
});

test('weather advisory report filters and summarizes maritime risk levels', function () {
    $response = $this->actingAs($this->superadmin)->getJson('/admin/reports/preview?'.http_build_query([
        'type' => 'weather-advisory',
        'date_from' => Carbon::now()->subDays(1)->format('Y-m-d'),
        'date_to' => Carbon::now()->addDays(10)->format('Y-m-d'),
    ]));

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.summary.total_forecasted_dates', 1);
    $response->assertJsonPath('data.summary.safe_green_count', 1);
    $response->assertJsonPath('data.records.0.advisory_level', 'green');
});

test('empty report handles zero records without error and generates valid PDF', function () {
    $response = $this->actingAs($this->superadmin)->get('/admin/reports/download?'.http_build_query([
        'type' => 'booking',
        'date_from' => '1990-01-01',
        'date_to' => '1990-01-02',
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

test('pdf download returns correct content-type and descriptive attachment filename', function () {
    $response = $this->actingAs($this->superadmin)->get('/admin/reports/download?'.http_build_query([
        'type' => 'booking',
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-30',
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    $response->assertHeader('content-disposition', 'attachment; filename=booking-report-2026-09-01-to-2026-09-30.pdf');
});
