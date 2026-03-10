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
        Schema::create('leave_balance_refresh_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('refresh_year');
            $table->decimal('balance_before', 8, 2)->default(0);
            $table->decimal('allocation_added', 8, 2)->default(0);
            $table->decimal('balance_after', 8, 2)->default(0);
            $table->foreignId('refreshed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('annual');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'refresh_year'], 'leave_refresh_unique_user_year');
            $table->index(['refresh_year', 'source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_balance_refresh_logs');
    }
};

