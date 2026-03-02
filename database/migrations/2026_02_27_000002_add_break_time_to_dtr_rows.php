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
        Schema::table('dtr_rows', function (Blueprint $table) {
            $table->integer('break_minutes')->default(0)->after('total_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dtr_rows', function (Blueprint $table) {
            $table->dropColumn(['break_minutes']);
        });
    }
};
