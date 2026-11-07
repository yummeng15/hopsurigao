<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                ->constrained('bookings')
                ->cascadeOnDelete();

            $table->decimal('amount', 10, 2);

            $table->string('payment_method');

            $table->enum('payment_status', [
                'pending',
                'paid',
                'failed',
                'refunded',
                'cancelled',
            ])->default('pending');

            $table->string('transaction_reference')->nullable();


            // Additional payment information
            $table->text('notes')->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};