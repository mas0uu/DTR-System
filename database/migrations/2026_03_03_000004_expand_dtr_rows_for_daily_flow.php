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
        Schema::table('dtr_rows', function (Blueprint $table) {
            $table->integer('late_minutes')->default(0)->after('break_minutes');
            $table->boolean('on_break')->default(false)->after('late_minutes');
            $table->timestamp('break_started_at')->nullable()->after('on_break');
            $table->integer('break_target_minutes')->nullable()->after('break_started_at');
        });

        DB::statement("
            ALTER TABLE dtr_rows
            MODIFY status ENUM('draft', 'in_progress', 'finished', 'leave', 'missed') NOT NULL DEFAULT 'draft'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE dtr_rows
            MODIFY status ENUM('draft', 'finished') NOT NULL DEFAULT 'draft'
        ");

        Schema::table('dtr_rows', function (Blueprint $table) {
            $table->dropColumn([
                'late_minutes',
                'on_break',
                'break_started_at',
                'break_target_minutes',
            ]);
        });
    }
};
