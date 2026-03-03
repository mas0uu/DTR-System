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
            $table->string('employee_type')->nullable()->after('supervisor_position');
            $table->date('starting_date')->nullable()->after('employee_type');
            $table->json('working_days')->nullable()->after('starting_date');
            $table->time('work_time_in')->nullable()->after('working_days');
            $table->time('work_time_out')->nullable()->after('work_time_in');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'employee_type',
                'starting_date',
                'working_days',
                'work_time_in',
                'work_time_out',
            ]);
        });
    }
};
