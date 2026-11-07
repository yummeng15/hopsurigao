<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pickup_locations', function (Blueprint $table) {
            if (! Schema::hasColumn('pickup_locations', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('address');
            }

            if (! Schema::hasColumn('pickup_locations', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });

        $pins = [
            'Dapa Port Departure Hub' => [9.7574000, 126.0528000],
            'General Luna Tourism Boulevard' => [9.8004000, 126.1622000],
            'General Luna Public Market Pier' => [9.8032000, 126.1601000],
            'Del Carmen Municipal Tourism Center' => [9.8688000, 126.0264000],
            'Cloud 9 Boardwalk Entrance' => [9.8087000, 126.1680000],
            'Sayak Airport Terminal' => [9.8591000, 126.0140000],
        ];

        foreach ($pins as $name => [$latitude, $longitude]) {
            DB::table('pickup_locations')
                ->where('name', $name)
                ->whereNull('latitude')
                ->update([
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pickup_locations', function (Blueprint $table) {
            if (Schema::hasColumn('pickup_locations', 'longitude')) {
                $table->dropColumn('longitude');
            }

            if (Schema::hasColumn('pickup_locations', 'latitude')) {
                $table->dropColumn('latitude');
            }
        });
    }
};
