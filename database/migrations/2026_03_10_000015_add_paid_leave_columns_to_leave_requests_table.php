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
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->decimal('requested_days', 8, 2)->default(1)->after('request_type');
            $table->boolean('is_paid')->default(false)->after('requested_days');
            $table->decimal('approved_paid_days', 8, 2)->default(0)->after('is_paid');
            $table->decimal('approved_unpaid_days', 8, 2)->default(0)->after('approved_paid_days');
            $table->decimal('deducted_days', 8, 2)->default(0)->after('approved_unpaid_days');
            $table->decimal('balance_before', 8, 2)->nullable()->after('deducted_days');
            $table->decimal('balance_after', 8, 2)->nullable()->after('balance_before');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn([
                'requested_days',
                'is_paid',
                'approved_paid_days',
                'approved_unpaid_days',
                'deducted_days',
                'balance_before',
                'balance_after',
            ]);
        });
    }
};

