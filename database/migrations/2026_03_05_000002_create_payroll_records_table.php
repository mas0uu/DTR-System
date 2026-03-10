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
        Schema::create('payroll_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('pay_period_start');
            $table->date('pay_period_end');
            $table->string('salary_type');
            $table->decimal('salary_amount', 12, 2);
            $table->decimal('days_worked', 8, 2)->default(0);
            $table->decimal('hours_worked', 10, 2)->default(0);
            $table->unsignedInteger('absences')->default(0);
            $table->unsignedInteger('undertime_minutes')->default(0);
            $table->unsignedInteger('half_days')->default(0);
            $table->decimal('total_salary', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'pay_period_start', 'pay_period_end'], 'payroll_records_user_period_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_records');
    }
};
