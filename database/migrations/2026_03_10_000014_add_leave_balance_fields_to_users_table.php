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
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('initial_paid_leave_days', 8, 2)->default(0)->after('salary_amount');
            $table->decimal('current_paid_leave_balance', 8, 2)->default(0)->after('initial_paid_leave_days');
            $table->unsignedTinyInteger('leave_reset_month')->default(1)->after('current_paid_leave_balance');
            $table->unsignedTinyInteger('leave_reset_day')->default(1)->after('leave_reset_month');
            $table->unsignedSmallInteger('last_leave_refresh_year')->nullable()->after('leave_reset_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'initial_paid_leave_days',
                'current_paid_leave_balance',
                'leave_reset_month',
                'leave_reset_day',
                'last_leave_refresh_year',
            ]);
        });
    }
};

