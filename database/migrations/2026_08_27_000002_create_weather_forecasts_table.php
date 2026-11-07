<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('weather_forecasts')) {
            Schema::create('weather_forecasts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tour_date_id')
                    ->constrained('tour_dates')
                    ->cascadeOnDelete();
                $table->date('forecast_date')->index();
                $table->string('location')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->decimal('temperature_min', 5, 2)->nullable();
                $table->decimal('temperature_max', 5, 2)->nullable();
                $table->decimal('temperature_current', 5, 2)->nullable();
                $table->string('weather_condition')->default('Fair Weather');
                $table->integer('weather_code')->nullable();
                $table->integer('rainfall_probability')->default(0);
                $table->decimal('rainfall', 6, 2)->default(0);
                $table->decimal('wind_speed', 5, 2)->default(0);
                $table->decimal('wind_gusts', 5, 2)->nullable();
                $table->integer('wind_direction')->nullable();
                $table->integer('humidity')->nullable();
                $table->enum('advisory_level', ['green', 'yellow', 'orange', 'red'])->default('green')->index();
                $table->string('advisory_badge')->default('Suitable');
                $table->text('advisory_message')->nullable();
                $table->boolean('is_outdated')->default(false);
                $table->timestamp('fetched_at')->nullable()->index();
                $table->timestamps();

                $table->unique(['tour_date_id', 'forecast_date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weather_forecasts');
    }
};

