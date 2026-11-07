<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PaymentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $paymentMethods = ['GCash', 'Maya', 'Bank Transfer', 'Cash', 'Credit Card'];

        // Clear existing payments before seeding
        Payment::query()->delete();

        Booking::with(['tourDate.package'])->get()->each(function (Booking $booking) use ($paymentMethods) {
            $packagePrice = $booking->tourDate?->package?->price ?? 1500;
            $totalAmount = $booking->number_of_guests * $packagePrice;
            $method = fake()->randomElement($paymentMethods);

            $status = match ($booking->booking_status) {
                'completed', 'confirmed' => 'paid',
                'pending' => fake()->randomElement(['pending', 'paid']),
                'cancelled' => fake()->randomElement(['cancelled', 'refunded']),
                default => 'pending',
            };

            $refPrefix = match ($method) {
                'GCash' => 'GCASH',
                'Maya' => 'MAYA',
                'Bank Transfer' => 'BDO',
                'Credit Card' => 'CC',
                default => 'TXN',
            };

            $reference = in_array($status, ['paid', 'refunded'])
                ? $refPrefix . '-' . strtoupper(fake()->bothify('######??'))
                : null;

            $paidAt = in_array($status, ['paid', 'refunded'])
                ? Carbon::parse($booking->created_at)->addMinutes(fake()->numberBetween(15, 120))
                : null;

            $notes = match ($status) {
                'refunded' => 'Customer requested cancellation due to personal scheduling conflict. Full refund issued.',
                'cancelled' => 'Reservation cancelled prior to payment confirmation.',
                'paid' => 'Payment verified by administrator/operator.',
                default => 'Pending payment proof verification from customer.',
            };

            Payment::create([
                'booking_id'            => $booking->id,
                'amount'                => $totalAmount,
                'payment_method'        => $method,
                'payment_status'        => $status,
                'transaction_reference' => $reference,
                'notes'                 => $notes,
                'paid_at'               => $paidAt,
                'created_at'            => $booking->created_at,
                'updated_at'            => now(),
            ]);
        });
    }
}