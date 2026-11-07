<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_dates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('package_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->date('tour_date');

            // Maximum number of guests for this specific date
            $table->unsignedInteger('capacity');

            $table->timestamps();

            $table->unique(['package_id', 'tour_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_dates');
    }
};