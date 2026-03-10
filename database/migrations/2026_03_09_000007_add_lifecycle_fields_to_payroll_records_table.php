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
        Schema::table('payroll_records', function (Blueprint $table) {
            $table->enum('status', ['generated', 'reviewed', 'finalized'])->default('generated')->after('total_salary');
            $table->enum('source', ['admin', 'self'])->default('admin')->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('source')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->foreignId('finalized_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable()->after('finalized_by');
            $table->text('lock_reason')->nullable()->after('finalized_at');
            $table->json('input_snapshot')->nullable()->after('lock_reason');
            $table->unsignedInteger('correction_count')->default(0)->after('input_snapshot');

            $table->index(['user_id', 'status']);
            $table->index(['status', 'pay_period_start', 'pay_period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_records', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropForeign(['finalized_by']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['status', 'pay_period_start', 'pay_period_end']);
            $table->dropColumn([
                'status',
                'source',
                'reviewed_by',
                'reviewed_at',
                'finalized_by',
                'finalized_at',
                'lock_reason',
                'input_snapshot',
                'correction_count',
            ]);
        });
    }
};
