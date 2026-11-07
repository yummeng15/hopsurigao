<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickup_schedules', function (Blueprint $table) {
            $table->id();

            // Specific tour date this pickup schedule belongs to
            $table->foreignId('tour_date_id')
                  ->constrained('tour_dates')
                  ->cascadeOnDelete();

            // Pickup location
            $table->foreignId('pickup_location_id')
                  ->constrained('pickup_locations')
                  ->restrictOnDelete();

            // Pickup time for this specific tour date and location
            $table->time('pickup_time');

            $table->timestamps();

            // Prevent duplicate pickup schedules
            $table->unique([
                'tour_date_id',
                'pickup_location_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_schedules');
    }
};