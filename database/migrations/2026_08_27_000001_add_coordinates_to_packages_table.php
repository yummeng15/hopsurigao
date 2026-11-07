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
        Schema::table('packages', function (Blueprint $table) {
            if (!Schema::hasColumn('packages', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('destination');
            }
            if (!Schema::hasColumn('packages', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (Schema::hasColumn('packages', 'longitude')) {
                $table->dropColumn('longitude');
            }
            if (Schema::hasColumn('packages', 'latitude')) {
                $table->dropColumn('latitude');
            }
        });
    }
};

