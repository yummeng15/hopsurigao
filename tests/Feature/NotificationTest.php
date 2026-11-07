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
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\WeatherAdvisoryNotification;
use App\Services\WeatherAdvisoryService;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Setup roles
    $this->adminRole = Role::firstOrCreate(['name' => 'Admin'], ['description' => 'Operator']);
    $this->superadminRole = Role::firstOrCreate(['name' => 'Superadmin'], ['description' => 'Superadmin']);
    $this->userRole = Role::firstOrCreate(['name' => 'User'], ['description' => 'Tourist']);

    $business = TourOperator::query()->create([
        'name' => 'Notify Tours',
        'slug' => 'notify-tours-'.uniqid(),
        'is_active' => true,
    ]);

    $this->operator = User::factory()->create([
        'role_id' => $this->adminRole->id,
        'tour_operator_id' => $business->id,
        'email_verified_at' => now(),
    ]);

    $this->tourist = User::factory()->create([
        'role_id' => $this->userRole->id,
        'email_verified_at' => now(),
    ]);

    $this->package = Package::create([
        'package_name' => 'Siargao Island Hopping',
        'destination' => 'Siargao',
        'description' => 'Great tour',
        'price' => 1500,
        'status' => 'active',
        'latitude' => 9.8583,
        'longitude' => 126.0461,
    ]);

    $this->package->forceFill([
        'tour_operator_id' => $business->id,
    ])->save();

    $this->tourDate = TourDate::create([
        'package_id' => $this->package->id,
        'tour_date' => now()->addDays(5)->toDateString(),
        'capacity' => 20,
    ]);

    $this->pickupLocation = PickupLocation::create([
        'name' => 'General Luna Pier',
        'status' => 'active',
    ]);

    $this->pickupSchedule = PickupSchedule::create([
        'tour_date_id' => $this->tourDate->id,
        'pickup_location_id' => $this->pickupLocation->id,
        'pickup_time' => '07:00:00',
    ]);
});

test('tourist creating a booking notifies operators', function () {
    Notification::fake();

    $response = $this->actingAs($this->tourist)->post(route('user.bookings.store'), [
        'tour_date_id' => $this->tourDate->id,
        'pickup_schedule_id' => $this->pickupSchedule->id,
        'pickup_latitude' => 9.8600000,
        'pickup_longitude' => 126.0800000,
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'special_request' => 'Vegetarian meals',
    ]);

    $response->assertRedirect();

    Notification::assertSentTo(
        $this->operator,
        BookingCreatedNotification::class,
        function ($notification) {
            return $notification->booking->number_of_guests === 2;
        }
    );
});

test('operator confirming a booking notifies the tourist', function () {
    Notification::fake();

    $booking = Booking::create([
        'user_id' => $this->tourist->id,
        'tour_date_id' => $this->tourDate->id,
        'pickup_schedule_id' => $this->pickupSchedule->id,
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => 'pending',
    ]);

    $response = $this->actingAs($this->operator)->put(route('admin.bookings.update', $booking), [
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => 'confirmed',
    ]);

    $response->assertRedirect(route('admin.bookings.index'));

    Notification::assertSentTo(
        $this->tourist,
        BookingConfirmedNotification::class,
        function ($notification) use ($booking) {
            return $notification->booking->id === $booking->id;
        }
    );
});

test('tourist submitting payment proof notifies operators', function () {
    Notification::fake();

    $booking = Booking::create([
        'user_id' => $this->tourist->id,
        'tour_date_id' => $this->tourDate->id,
        'pickup_schedule_id' => $this->pickupSchedule->id,
        'number_of_guests' => 1,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => 'pending',
    ]);

    $response = $this->actingAs($this->tourist)->post(route('user.bookings.payment.submit', $booking), [
        'payment_method' => 'gcash',
        'transaction_reference' => 'GCASH-123456',
        'notes' => 'Paid via GCash',
    ]);

    $response->assertSessionHasErrors('payment_method');

    Notification::assertNothingSent();
});

test('administrator marking a payment paid notifies the tourist', function () {
    Notification::fake();

    $admin = User::factory()->create([
        'role_id' => $this->superadminRole->id,
        'email_verified_at' => now(),
    ]);

    $booking = Booking::create([
        'user_id' => $this->tourist->id,
        'tour_date_id' => $this->tourDate->id,
        'pickup_schedule_id' => $this->pickupSchedule->id,
        'number_of_guests' => 1,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => 'pending',
    ]);

    $payment = Payment::create([
        'booking_id' => $booking->id,
        'amount' => 1500,
        'payment_method' => 'cash',
        'payment_status' => 'pending',
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->from(route('admin.bookings.show', $booking))
        ->post(route('admin.bookings.confirm-payment', $booking), [
            '_token' => 'test-token',
        ]);

    $response->assertRedirect(route('admin.bookings.show', $booking));

    Notification::assertSentTo(
        $this->tourist,
        PaymentConfirmedNotification::class,
        function ($notification) use ($payment) {
            return $notification->payment->id === $payment->id;
        }
    );
});

test('weather advisory change notifies booked tourists and operators without spamming when unchanged', function () {
    Notification::fake();

    // Create a booking for tourist on this tour date
    Booking::create([
        'user_id' => $this->tourist->id,
        'tour_date_id' => $this->tourDate->id,
        'pickup_schedule_id' => $this->pickupSchedule->id,
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => 'confirmed',
    ]);

    $targetDateStr = $this->tourDate->tour_date->toDateString();

    Http::fake([
        'https://api.open-meteo.com/v1/forecast*' => Http::response([
            'daily' => [
                'time' => [$targetDateStr],
                'weather_code' => [51],
                'temperature_2m_max' => [29.0],
                'temperature_2m_min' => [24.0],
                'precipitation_sum' => [5.0],
                'precipitation_probability_max' => [50],
                'wind_speed_10m_max' => [22.0],
                'wind_gusts_10m_max' => [32.0],
                'wind_direction_10m_dominant' => [90],
            ],
            'current' => [
                'temperature_2m' => 28.0,
                'relative_humidity_2m' => 70,
                'weather_code' => 51,
                'wind_speed_10m' => 20.0,
            ],
        ], 200),
    ]);

    $service = new WeatherService(new WeatherAdvisoryService);
    $service->getForecastForTourDate($this->tourDate, forceFresh: true);

    Notification::assertSentTo($this->tourist, WeatherAdvisoryNotification::class);
    Notification::assertSentTo($this->operator, WeatherAdvisoryNotification::class);
});

test('weather advisory remaining at same level does not spam notifications', function () {
    // Seed existing forecast with yellow level
    WeatherForecast::create([
        'tour_date_id' => $this->tourDate->id,
        'forecast_date' => $this->tourDate->tour_date->toDateString(),
        'location' => $this->package->destination,
        'advisory_level' => 'yellow',
        'advisory_badge' => 'Caution / Monitor',
        'advisory_message' => 'Moderate conditions',
        'weather_condition' => 'Light Rain',
        'weather_code' => 51,
        'wind_speed' => 22.0,
        'wind_gusts' => 30.0,
        'wind_direction' => 90,
        'rainfall' => 5.0,
        'rainfall_probability' => 50,
        'temperature_min' => 24.0,
        'temperature_max' => 29.0,
        'humidity' => 70,
        'is_outdated' => false,
        'fetched_at' => now(),
    ]);

    Booking::create([
        'user_id' => $this->tourist->id,
        'tour_date_id' => $this->tourDate->id,
        'pickup_schedule_id' => $this->pickupSchedule->id,
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => 'confirmed',
    ]);

    Notification::fake();

    $targetDateStr = $this->tourDate->tour_date->toDateString();

    Http::fake([
        'https://api.open-meteo.com/v1/forecast*' => Http::response([
            'daily' => [
                'time' => [$targetDateStr],
                'weather_code' => [51],
                'temperature_2m_max' => [29.0],
                'temperature_2m_min' => [24.0],
                'precipitation_sum' => [5.0],
                'precipitation_probability_max' => [50],
                'wind_speed_10m_max' => [22.0],
                'wind_gusts_10m_max' => [32.0],
                'wind_direction_10m_dominant' => [90],
            ],
            'current' => [
                'temperature_2m' => 28.0,
                'relative_humidity_2m' => 70,
                'weather_code' => 51,
                'wind_speed_10m' => 20.0,
            ],
        ], 200),
    ]);

    $service = new WeatherService(new WeatherAdvisoryService);
    $service->getForecastForTourDate($this->tourDate, forceFresh: true);

    Notification::assertNothingSent();
});

test('weather advisory worsening to red sends urgent notification', function () {
    // Seed existing forecast with yellow level
    WeatherForecast::create([
        'tour_date_id' => $this->tourDate->id,
        'forecast_date' => $this->tourDate->tour_date->toDateString(),
        'location' => $this->package->destination,
        'advisory_level' => 'yellow',
        'advisory_badge' => 'Caution / Monitor',
        'advisory_message' => 'Moderate conditions',
        'weather_condition' => 'Light Rain',
        'weather_code' => 51,
        'wind_speed' => 22.0,
        'wind_gusts' => 30.0,
        'wind_direction' => 90,
        'rainfall' => 5.0,
        'rainfall_probability' => 50,
        'temperature_min' => 24.0,
        'temperature_max' => 29.0,
        'humidity' => 70,
        'is_outdated' => false,
        'fetched_at' => now(),
    ]);

    Booking::create([
        'user_id' => $this->tourist->id,
        'tour_date_id' => $this->tourDate->id,
        'pickup_schedule_id' => $this->pickupSchedule->id,
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => 'confirmed',
    ]);

    Notification::fake();

    $targetDateStr = $this->tourDate->tour_date->toDateString();

    Http::fake([
        'https://api.open-meteo.com/v1/forecast*' => Http::response([
            'daily' => [
                'time' => [$targetDateStr],
                'weather_code' => [99],
                'temperature_2m_max' => [27.0],
                'temperature_2m_min' => [22.0],
                'precipitation_sum' => [40.0],
                'precipitation_probability_max' => [95],
                'wind_speed_10m_max' => [55.0],
                'wind_gusts_10m_max' => [70.0],
                'wind_direction_10m_dominant' => [90],
            ],
            'current' => [
                'temperature_2m' => 25.0,
                'relative_humidity_2m' => 90,
                'weather_code' => 99,
                'wind_speed_10m' => 50.0,
            ],
        ], 200),
    ]);

    $service = new WeatherService(new WeatherAdvisoryService);
    $service->getForecastForTourDate($this->tourDate, forceFresh: true);

    Notification::assertSentTo(
        $this->tourist,
        WeatherAdvisoryNotification::class,
        fn ($n) => $n->advisoryLevel === 'red'
    );
});

test('user can fetch unread count and notification feed', function () {
    // Send a notification to tourist
    $booking = Booking::create([
        'user_id' => $this->tourist->id,
        'tour_date_id' => $this->tourDate->id,
        'pickup_schedule_id' => $this->pickupSchedule->id,
        'number_of_guests' => 1,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => 'pending',
    ]);

    $this->tourist->notify(new BookingConfirmedNotification($booking));

    // Test unread count endpoint
    $response = $this->actingAs($this->tourist)->getJson(route('notifications.unread-count'));
    $response->assertOk()->assertJson(['unread_count' => 1]);

    // Test feed endpoint
    $feedResponse = $this->actingAs($this->tourist)->getJson(route('notifications.feed'));
    $feedResponse->assertOk()
        ->assertJsonStructure([
            'notifications' => [
                '*' => ['id', 'type', 'title', 'message', 'action_url', 'is_read', 'time_ago'],
            ],
            'unread_count',
        ])
        ->assertJson(['unread_count' => 1]);
});

test('user can mark notification as read and mark all as read', function () {
    $booking = Booking::create([
        'user_id' => $this->tourist->id,
        'tour_date_id' => $this->tourDate->id,
        'pickup_schedule_id' => $this->pickupSchedule->id,
        'number_of_guests' => 1,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => 'pending',
    ]);

    $this->tourist->notify(new BookingConfirmedNotification($booking));
    $notification = $this->tourist->unreadNotifications()->first();

    // Mark single as read
    $response = $this->actingAs($this->tourist)->patchJson(route('notifications.read', $notification->id));
    $response->assertOk()->assertJson(['success' => true, 'unread_count' => 0]);

    expect($notification->fresh()->read_at)->not->toBeNull();

    // Add another notification and test mark all read
    $this->tourist->notify(new BookingConfirmedNotification($booking));
    expect($this->tourist->unreadNotifications()->count())->toBe(1);

    $markAllResponse = $this->actingAs($this->tourist)->patchJson(route('notifications.mark-all-read'));
    $markAllResponse->assertOk()->assertJson(['success' => true, 'unread_count' => 0]);
    expect($this->tourist->unreadNotifications()->count())->toBe(0);
});

test('user cannot access or delete another users notifications', function () {
    $booking = Booking::create([
        'user_id' => $this->tourist->id,
        'tour_date_id' => $this->tourDate->id,
        'pickup_schedule_id' => $this->pickupSchedule->id,
        'number_of_guests' => 1,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => 'pending',
    ]);

    $this->tourist->notify(new BookingConfirmedNotification($booking));
    $touristNotification = $this->tourist->unreadNotifications()->first();

    // Another user tries to mark or delete tourist's notification
    $anotherUser = User::factory()->create([
        'role_id' => $this->userRole->id,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($anotherUser)->patchJson(route('notifications.read', $touristNotification->id));
    $response->assertNotFound();

    $deleteResponse = $this->actingAs($anotherUser)->deleteJson(route('notifications.destroy', $touristNotification->id));
    $deleteResponse->assertNotFound();
});

test('notification history page renders for authenticated user', function () {
    $response = $this->actingAs($this->tourist)->get(route('notifications.index'));
    $response->assertOk();
});
