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
            $table->dropColumn('remarks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dtr_rows', function (Blueprint $table) {
            $table->string('remarks', 255)->nullable()->after('time_out');
        });
    }
};
