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
        Schema::table('booking_feedback', function (Blueprint $table) {
            $table->renameColumn('smoothness', 'enjoyment');
            $table->renameColumn('user_friendly', 'operator_service');
            $table->renameColumn('ease', 'overall_experience');
        });

        foreach (DB::table('booking_feedback')->get() as $row) {
            DB::table('booking_feedback')
                ->where('id', $row->id)
                ->update([
                    'enjoyment' => match ($row->enjoyment) {
                        'smooth' => 'enjoyed',
                        'not_smooth' => 'not_enjoyed',
                        default => $row->enjoyment,
                    },
                    'operator_service' => match ($row->operator_service) {
                        'yes' => 'good',
                        'somewhat' => 'okay',
                        'no' => 'poor',
                        default => $row->operator_service,
                    },
                    'overall_experience' => match ($row->overall_experience) {
                        'easy' => 'great',
                        'difficult' => 'poor',
                        default => $row->overall_experience,
                    },
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (DB::table('booking_feedback')->get() as $row) {
            DB::table('booking_feedback')
                ->where('id', $row->id)
                ->update([
                    'enjoyment' => match ($row->enjoyment) {
                        'enjoyed' => 'smooth',
                        'not_enjoyed' => 'not_smooth',
                        default => $row->enjoyment,
                    },
                    'operator_service' => match ($row->operator_service) {
                        'good' => 'yes',
                        'okay' => 'somewhat',
                        'poor' => 'no',
                        default => $row->operator_service,
                    },
                    'overall_experience' => match ($row->overall_experience) {
                        'great', 'okay' => 'easy',
                        'poor' => 'difficult',
                        default => $row->overall_experience,
                    },
                ]);
        }

        Schema::table('booking_feedback', function (Blueprint $table) {
            $table->renameColumn('enjoyment', 'smoothness');
            $table->renameColumn('operator_service', 'user_friendly');
            $table->renameColumn('overall_experience', 'ease');
        });
    }
};
