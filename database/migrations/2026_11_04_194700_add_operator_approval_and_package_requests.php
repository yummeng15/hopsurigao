<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_operators', function (Blueprint $table): void {
            if (! Schema::hasColumn('tour_operators', 'approval_status')) {
                $table->string('approval_status', 32)->default('active')->after('is_active');
            }

            if (! Schema::hasColumn('tour_operators', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approval_status');
            }
        });

        if (Schema::hasColumn('tour_operators', 'approval_status')) {
            DB::table('tour_operators')
                ->where('is_active', true)
                ->update(['approval_status' => 'active']);

            DB::table('tour_operators')
                ->where('is_active', false)
                ->update(['approval_status' => 'suspended']);
        }

        if (! Schema::hasTable('package_ownership_requests')) {
            Schema::create('package_ownership_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
                $table->foreignId('tour_operator_id')->constrained('tour_operators')->cascadeOnDelete();
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                $table->string('status', 32)->default('pending');
                $table->text('review_note')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('package_ownership_requests');

        Schema::table('tour_operators', function (Blueprint $table): void {
            if (Schema::hasColumn('tour_operators', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }

            if (Schema::hasColumn('tour_operators', 'approval_status')) {
                $table->dropColumn('approval_status');
            }
        });
    }
};
