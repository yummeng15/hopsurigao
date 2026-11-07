<?php

use App\Models\Booking;
use App\Models\BookingFeedback;
use App\Models\Payment;
use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use App\Notifications\BookingFeedbackSubmittedNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    config()->set('inertia.ssr.enabled', false);
    $this->withoutVite();
});

/**
 * @return array{booking: Booking, tourist: User, operatorUser: User, adminUser: User, dateId: int}
 */
function feedbackFixture(string $tourDate = 'past'): array
{
    $touristRole = Role::query()->firstOrCreate(['name' => 'User']);
    $operatorRole = Role::query()->firstOrCreate(['name' => 'Admin']);
    $adminRole = Role::query()->firstOrCreate(['name' => 'Superadmin']);

    $operator = TourOperator::query()->create([
        'name' => 'Feedback Test Operator',
        'slug' => 'feedback-test-'.Str::uuid(),
        'is_active' => true,
        'approval_status' => TourOperator::STATUS_ACTIVE,
    ]);

    $packageId = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Feedback Island Tour',
        'boat_name' => 'Feedback Boat',
        'destination' => 'Britania',
        'price' => '1500.00',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dateValue = $tourDate === 'future'
        ? today()->addDays(12)->toDateString()
        : today()->subDay()->toDateString();

    $dateId = DB::table('tour_dates')->insertGetId([
        'package_id' => $packageId,
        'tour_date' => $dateValue,
        'capacity' => 20,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $locationId = DB::table('pickup_locations')->insertGetId([
        'name' => 'Feedback Pier',
        'tour_operator_id' => $operator->id,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pickupId = DB::table('pickup_schedules')->insertGetId([
        'tour_date_id' => $dateId,
        'pickup_location_id' => $locationId,
        'pickup_time' => '07:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
        'name' => 'Feedback Guest',
    ]);

    $operatorUser = User::factory()->create([
        'role_id' => $operatorRole->id,
        'tour_operator_id' => $operator->id,
    ]);

    $adminUser = User::factory()->create([
        'role_id' => $adminRole->id,
    ]);

    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $dateId,
        'pickup_schedule_id' => $pickupId,
        'number_of_guests' => 1,
        'phone_number' => '09175550901',
        'nationality' => 'Filipino',
        'booking_status' => 'confirmed',
    ]);

    Payment::query()->create([
        'booking_id' => $booking->id,
        'amount' => 1500,
        'payment_method' => 'gcash',
        'payment_status' => 'paid',
    ]);

    return [
        'booking' => $booking,
        'tourist' => $tourist,
        'operatorUser' => $operatorUser,
        'adminUser' => $adminUser,
        'dateId' => $dateId,
    ];
}

test('a tourist can open feedback from the navbar after the tour date', function () {
    $fixture = feedbackFixture('past');
    $booking = $fixture['booking'];

    $this->actingAs($fixture['tourist'])
        ->get(route('user.feedback.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/feedback/Index')
            ->where('bookings.0.id', $booking->id)
            ->where('bookings.0.has_feedback', false));

    $this->actingAs($fixture['tourist'])
        ->get(route('user.bookings.feedback.create', $booking))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/bookings/Feedback')
            ->where('booking.id', $booking->id));

    Notification::fake();

    $this->actingAs($fixture['tourist'])
        ->withSession(['_token' => 'test-token'])
        ->post(route('user.bookings.feedback.store', $booking), [
            '_token' => 'test-token',
            'enjoyment' => 'enjoyed',
            'operator_service' => 'good',
            'overall_experience' => 'great',
            'comment' => 'The island stops were beautiful and the crew was helpful.',
        ])
        ->assertRedirect(route('user.feedback.index'));

    $feedback = BookingFeedback::query()->where('booking_id', $booking->id)->first();

    expect($feedback)->not->toBeNull()
        ->and($feedback->enjoyment)->toBe('enjoyed')
        ->and($feedback->operator_service)->toBe('good')
        ->and($feedback->overall_experience)->toBe('great');

    Notification::assertSentTo(
        $fixture['operatorUser'],
        BookingFeedbackSubmittedNotification::class,
        fn (BookingFeedbackSubmittedNotification $notification): bool => $notification->booking->is($booking)
            && $notification->feedback->is($feedback)
    );

    Notification::assertSentTo(
        $fixture['adminUser'],
        BookingFeedbackSubmittedNotification::class
    );

    $this->actingAs($fixture['operatorUser'])
        ->get(route('operator.operations.feedback'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('operator/Operations')
            ->where('section', 'feedback')
            ->where('feedback.0.guest_name', 'Feedback Guest')
            ->where('feedback.0.overall_experience', 'great'));

    $this->actingAs($fixture['adminUser'])
        ->get(route('admin.booking-feedback.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/feedback/Index')
            ->where('feedback.0.booking_id', $booking->id)
            ->where('feedback.0.enjoyment', 'enjoyed'));
});

test('feedback stays closed until the tour date has passed', function () {
    $fixture = feedbackFixture('future');
    $booking = $fixture['booking'];

    $this->actingAs($fixture['tourist'])
        ->get(route('user.feedback.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/feedback/Index')
            ->where('bookings', []));

    $this->actingAs($fixture['tourist'])
        ->get(route('user.bookings.feedback.create', $booking))
        ->assertRedirect(route('user.feedback.index'));
});

test('a tourist cannot send feedback for someone else booking', function () {
    $fixture = feedbackFixture('past');
    $other = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'User'])->id,
    ]);

    $this->actingAs($other)
        ->withSession(['_token' => 'test-token'])
        ->post(route('user.bookings.feedback.store', $fixture['booking']), [
            '_token' => 'test-token',
            'enjoyment' => 'not_enjoyed',
            'operator_service' => 'poor',
            'overall_experience' => 'poor',
        ])
        ->assertForbidden();

    expect(BookingFeedback::query()->where('booking_id', $fixture['booking']->id)->exists())->toBeFalse();
});

test('payment return goes to booking details instead of feedback', function () {
    $fixture = feedbackFixture('future');
    $booking = $fixture['booking'];

    $this->actingAs($fixture['tourist'])
        ->get(route('user.bookings.show', $booking))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/bookings/Show')
            ->missing('feedback'));
});

test('a tourist cannot open the staff feedback list', function () {
    $tourist = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'User'])->id,
    ]);

    $this->actingAs($tourist)
        ->get(route('admin.booking-feedback.index'))
        ->assertForbidden();
});
