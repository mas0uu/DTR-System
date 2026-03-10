<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('holidays')
            ->where('holiday_type', 'company')
            ->update(['holiday_type' => 'special']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `holidays` MODIFY `holiday_type` ENUM('regular','special') NOT NULL DEFAULT 'regular'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `holidays` MODIFY `holiday_type` ENUM('regular','special','company') NOT NULL DEFAULT 'regular'");
        }
    }
};
