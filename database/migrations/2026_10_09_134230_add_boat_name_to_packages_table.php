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
        Schema::table('packages', function (Blueprint $table) {
            $table->string('boat_name')->nullable()->after('package_name');
        });

        $boats = [
            'Siargao Tri-Island Hopping' => 'Daku Bangka',
            'Sugba Lagoon Adventure' => 'Sugba Bangka',
            'Sohoton Bucas Grande Adventure' => 'Sohoton Bangka',
            'Magpupungko Rock Pools Explorer' => 'Magpupungko Bangka',
            'Siargao Island Land & Sea Tour' => 'Maasin Bangka',
            'Siargao Sunset Coastal Discovery' => 'Luna Bangka',
            'Surigao City Island Hopping' => 'Basul Bangka',
            'Silop Cave Explorer' => 'Silop Bangka',
        ];

        foreach ($boats as $packageName => $boatName) {
            DB::table('packages')
                ->where('package_name', $packageName)
                ->whereNull('boat_name')
                ->update(['boat_name' => $boatName]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('boat_name');
        });
    }
};
