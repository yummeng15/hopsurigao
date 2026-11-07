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
        Schema::table('tour_dates', function (Blueprint $table) {
            $table->boolean('travel_closed')->default(false)->after('capacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_dates', function (Blueprint $table) {
            $table->dropColumn('travel_closed');
        });
    }
};
