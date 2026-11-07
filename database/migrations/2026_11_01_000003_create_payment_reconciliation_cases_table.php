
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'payment_reconciliation_cases',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'booking_id'
                )->index();

                $table->unsignedBigInteger(
                    'payment_id'
                )->nullable();

                $table->string(
                    'gateway_checkout_id',
                    191
                );

                $table->string(
                    'gateway_payment_id',
                    191
                )->unique();

                $table->decimal(
                    'amount',
                    14,
                    2
                );

                $table->string(
                    'reason',
                    64
                );

                $table->string(
                    'status',
                    32
                )->default('needs_review');

                $table->timestamps();

                // No cascading deletes for financial audit records.
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'payment_reconciliation_cases'
        );
    }
};
