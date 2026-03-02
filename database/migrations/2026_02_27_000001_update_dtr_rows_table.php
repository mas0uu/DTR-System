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
            $table->string('day')->nullable();
            $table->integer('total_minutes')->default(0);
            $table->enum('status', ['draft', 'finished'])->default('draft');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dtr_rows', function (Blueprint $table) {
            $table->dropColumn(['day', 'total_minutes', 'status']);
        });
    }
};
