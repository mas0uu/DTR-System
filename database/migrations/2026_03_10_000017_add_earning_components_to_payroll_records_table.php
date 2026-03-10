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
        Schema::table('payroll_records', function (Blueprint $table) {
            $table->decimal('base_pay', 12, 2)->default(0)->after('half_days');
            $table->decimal('paid_leave_pay', 12, 2)->default(0)->after('base_pay');
            $table->decimal('paid_holiday_base_pay', 12, 2)->default(0)->after('paid_leave_pay');
            $table->decimal('holiday_attendance_bonus', 12, 2)->default(0)->after('paid_holiday_base_pay');
            $table->decimal('leave_deductions', 12, 2)->default(0)->after('holiday_attendance_bonus');
            $table->decimal('other_deductions', 12, 2)->default(0)->after('leave_deductions');
            $table->decimal('total_deductions', 12, 2)->default(0)->after('other_deductions');
            $table->decimal('net_pay', 12, 2)->default(0)->after('total_deductions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_records', function (Blueprint $table) {
            $table->dropColumn([
                'base_pay',
                'paid_leave_pay',
                'paid_holiday_base_pay',
                'holiday_attendance_bonus',
                'leave_deductions',
                'other_deductions',
                'total_deductions',
                'net_pay',
            ]);
        });
    }
};

