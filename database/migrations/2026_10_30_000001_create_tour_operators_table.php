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
        Schema::create('tour_operators', function (Blueprint $table) {

            // Use InnoDB for foreign key support.
            $table->engine = 'InnoDB';

            // Primary key
            $table->id();

            // Tour operator information
            $table->string('name');

            // FIX: Limit slug to 191 characters.
            $table->string('slug', 191)->unique();

            $table->text('description')->nullable();

            $table->string('contact_email')->nullable();

            $table->string('contact_phone', 30)->nullable();

            // Account status
            $table->boolean('is_active')->default(true);

            // Timestamps
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_operators');
    }
};