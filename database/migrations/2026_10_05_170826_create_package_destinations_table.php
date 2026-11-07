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
        Schema::create('package_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE packages MODIFY destination TEXT NOT NULL');

        $now = now();

        foreach (DB::table('packages')->select('id', 'destination')->get() as $package) {
            $name = trim((string) $package->destination);

            if ($name === '') {
                continue;
            }

            DB::table('package_destinations')->insert([
                'package_id' => $package->id,
                'name' => $name,
                'position' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_destinations');

        DB::statement('ALTER TABLE packages MODIFY destination VARCHAR(255) NOT NULL');
    }
};
