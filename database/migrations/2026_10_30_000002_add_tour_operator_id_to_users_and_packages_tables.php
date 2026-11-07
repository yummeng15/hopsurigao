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
        /**
         * Add operator ownership to users.
         *
         * Superadmin and tourists do not need
         * to belong to one specific tour operator.
         */
        Schema::table('users', function (Blueprint $table) {

            $table->foreignId('tour_operator_id')
                ->nullable()
                ->after('id')
                ->constrained('tour_operators')
                ->nullOnDelete();

        });

        /**
         * Add operator ownership to packages.
         *
         * Every package will eventually belong
         * to a specific tour operator.
         */
        Schema::table('packages', function (Blueprint $table) {

            $table->foreignId('tour_operator_id')
                ->nullable()
                ->after('id')
                ->constrained('tour_operators')
                ->nullOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {

            $table->dropForeign([
                'tour_operator_id'
            ]);

            $table->dropColumn(
                'tour_operator_id'
            );

        });

        Schema::table('users', function (Blueprint $table) {

            $table->dropForeign([
                'tour_operator_id'
            ]);

            $table->dropColumn(
                'tour_operator_id'
            );

        });
    }
};