
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Check Existing Payment References
        |--------------------------------------------------------------------------
        |
        | Do not silently delete or combine financial
        | records that already have duplicate references.
        |
        | If duplicates exist, stop the migration
        | so they can be investigated first.
        |
        */

        $duplicate = DB::table('payments')
            ->select(
                'booking_id',
                'transaction_reference'
            )
            ->whereNotNull(
                'transaction_reference'
            )
            ->groupBy(
                'booking_id',
                'transaction_reference'
            )
            ->havingRaw(
                'COUNT(*) > 1'
            )
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException(
                'Duplicate payment references exist for booking #'
                .$duplicate->booking_id
                .'. Review the payment ledger before adding the unique index.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Add Database Unique Constraint
        |--------------------------------------------------------------------------
        |
        | Each booking can have many payments.
        |
        | But the same non-null transaction reference
        | cannot be recorded twice for one booking.
        |
        */

        Schema::table(
            'payments',
            function (Blueprint $table): void {
                $table->unique(
                    [
                        'booking_id',
                        'transaction_reference',
                    ],
                    'payments_booking_reference_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'payments',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'payments_booking_reference_unique'
                );
            }
        );
    }
};
