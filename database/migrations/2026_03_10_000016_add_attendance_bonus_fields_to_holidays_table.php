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
        Schema::table('holidays', function (Blueprint $table) {
            $table->boolean('has_attendance_bonus')->default(false)->after('is_paid');
            $table->enum('attendance_bonus_type', ['fixed_amount', 'percent_of_daily_rate'])->nullable()->after('has_attendance_bonus');
            $table->decimal('attendance_bonus_value', 10, 2)->nullable()->after('attendance_bonus_type');
        });

        DB::table('holidays')
            ->where('is_paid', true)
            ->where('holiday_type', 'regular')
            ->update([
                'has_attendance_bonus' => true,
                'attendance_bonus_type' => 'percent_of_daily_rate',
                'attendance_bonus_value' => 100,
            ]);

        DB::table('holidays')
            ->where('is_paid', true)
            ->where('holiday_type', 'special')
            ->update([
                'has_attendance_bonus' => true,
                'attendance_bonus_type' => 'percent_of_daily_rate',
                'attendance_bonus_value' => 30,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropColumn([
                'has_attendance_bonus',
                'attendance_bonus_type',
                'attendance_bonus_value',
            ]);
        });
    }
};
