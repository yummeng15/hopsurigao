<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->text('description');
            $table->timestamps();
        });

        $catalog = [
            'Siargao Tri-Island Hopping' => [
                'Island hopping across three islands',
                'Swimming and beach time',
                'Sightseeing',
                'Photo stops',
                'Picnic lunch on the island',
            ],
            'Sugba Lagoon Adventure' => [
                'Lagoon visit',
                'Paddleboarding',
                'Swimming',
                'Cliff jump from the springboard',
                'Mangrove viewing',
            ],
            'Sohoton Bucas Grande Adventure' => [
                'Cove and lagoon exploration',
                'Cave passage by paddle boat',
                'Jellyfish sanctuary visit',
                'Swimming',
                'Photo stops',
            ],
            'Magpupungko Rock Pools Explorer' => [
                'Rock pool swimming',
                'Beach time',
                'Viewing the rock formation',
                'Photo stops',
            ],
            'Siargao Island Land & Sea Tour' => [
                'Viewpoint stops',
                'River visit and rope swing',
                'Beach time',
                'Photo stops',
            ],
            'Siargao Sunset Coastal Discovery' => [
                'Sunset cruise',
                'Coastal sightseeing',
                'Photo stops',
                'Light snacks on board',
            ],
            'Surigao City Island Hopping' => [
                'Island hopping',
                'Swimming',
                'Cave exploration',
                'Beach time',
                'Photo stops',
            ],
            'Silop Cave Explorer' => [
                'Guided cave walk',
                'Pebble beach time',
                'Photo stops',
            ],
        ];

        $now = now();

        foreach ($catalog as $packageName => $activities) {
            $packageId = DB::table('packages')->where('package_name', $packageName)->value('id');

            if ($packageId === null) {
                continue;
            }

            if (DB::table('activities')->where('package_id', $packageId)->exists()) {
                continue;
            }

            foreach ($activities as $description) {
                DB::table('activities')->insert([
                    'package_id' => $packageId,
                    'description' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
