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
            $table->string('student_name')->nullable();
            $table->string('student_no')->unique()->nullable();
            $table->string('school')->nullable();
            $table->integer('required_hours')->default(0);
            $table->string('company')->nullable();
            $table->string('department')->nullable();
            $table->string('supervisor_name')->nullable();
            $table->string('supervisor_position')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'student_name',
                'student_no',
                'school',
                'required_hours',
                'company',
                'department',
                'supervisor_name',
                'supervisor_position',
            ]);
        });
    }
};
