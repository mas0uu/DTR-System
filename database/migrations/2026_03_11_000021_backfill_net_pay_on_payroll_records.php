<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('payroll_records')
            ->where('total_salary', '>', 0)
            ->where(function ($query) {
                $query->whereNull('net_pay')
                    ->orWhere('net_pay', '=', 0);
            })
            ->update([
                'net_pay' => DB::raw('ROUND(total_salary - total_deductions, 2)'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: historical net pay values should be preserved.
    }
};
