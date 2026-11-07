<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            // Lead traveler / user who made the booking
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Specific tour date
            $table->foreignId('tour_date_id')
                ->constrained('tour_dates')
                ->restrictOnDelete();

            // Exact pickup schedule selected for this booking
            $table->foreignId('pickup_schedule_id')
                ->constrained('pickup_schedules')
                ->restrictOnDelete();

            // Total number of travelers, including lead traveler
            $table->unsignedInteger('number_of_guests');

            // Booking-specific contact information
            $table->string('phone_number');

            $table->string('nationality');

            // Optional request
            $table->text('special_request')->nullable();

            // Booking status
            $table->enum('booking_status', [
                'pending',
                'confirmed',
                'cancelled',
                'completed',
            ])->default('pending');

            // Unique token used for booking QR code
            $table->string('qr_token')->unique()->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
