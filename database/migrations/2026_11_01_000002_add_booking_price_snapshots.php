
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->decimal('booked_unit_price', 12, 2)->nullable();
            $table->decimal('booked_total', 14, 2)->nullable();
            $table->string('price_snapshot_source', 24)->nullable();
        });

        /*
         * Existing bookings have no historical price snapshot. Freeze
         * their currently linked package price, but mark the result
         * as an ESTIMATE. Do not misrepresent it as the original quote.
         * Review older paid bookings against receipts before launch.
         */
        DB::table('bookings')
            ->join('tour_dates', 'tour_dates.id', '=', 'bookings.tour_date_id')
            ->join('packages', 'packages.id', '=', 'tour_dates.package_id')
            ->select(
                'bookings.id as id',
                'bookings.number_of_guests',
                'packages.price'
            )
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $unitCents = (int) round((float) $row->price * 100);
                    $totalCents = $unitCents * (int) $row->number_of_guests;

                    DB::table('bookings')
                        ->where('id', $row->id)
                        ->whereNull('booked_total')
                        ->update([
                            'booked_unit_price' => number_format($unitCents / 100, 2, '.', ''),
                            'booked_total' => number_format($totalCents / 100, 2, '.', ''),
                            'price_snapshot_source' => 'legacy_estimate',
                        ]);
                }
            }, 'bookings.id', 'id');
    }

    public function down(): void
    {
        // Rolling this migration back will discard frozen prices.
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn([
                'booked_unit_price',
                'booked_total',
                'price_snapshot_source',
            ]);
        });
    }
};
