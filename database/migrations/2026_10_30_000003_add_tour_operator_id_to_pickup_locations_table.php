<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pickup_locations', function (Blueprint $table) {
            $table->foreignId('tour_operator_id')
                ->nullable()
                ->after('id')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('pickup_locations', function (Blueprint $table) {
            $table->dropIndex(['tour_operator_id']);
            $table->dropColumn('tour_operator_id');
        });
    }
};