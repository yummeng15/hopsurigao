<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('times', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tour_date_id')
                  ->constrained('tour_dates')
                  ->cascadeOnDelete();

            $table->time('time');
            $table->string('description');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('times');
    }
};