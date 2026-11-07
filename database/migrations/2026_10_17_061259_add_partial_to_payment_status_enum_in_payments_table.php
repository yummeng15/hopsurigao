<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `payments` MODIFY COLUMN `payment_status` ENUM('pending', 'paid', 'partial', 'failed', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("UPDATE `payments` SET `payment_status` = 'paid' WHERE `payment_status` = 'partial'");
        DB::statement("ALTER TABLE `payments` MODIFY COLUMN `payment_status` ENUM('pending', 'paid', 'failed', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending'");
    }
};
