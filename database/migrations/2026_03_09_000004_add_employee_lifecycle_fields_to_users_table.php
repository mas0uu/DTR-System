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
            $table->enum('employment_status', ['active', 'inactive', 'archived'])
                ->default('active')
                ->after('is_admin');
            $table->timestamp('deactivated_at')->nullable()->after('employment_status');
            $table->foreignId('deactivated_by')->nullable()->after('deactivated_at')->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->after('deactivated_by');
            $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
            $table->text('status_reason')->nullable()->after('archived_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['deactivated_by']);
            $table->dropForeign(['archived_by']);
            $table->dropColumn([
                'employment_status',
                'deactivated_at',
                'deactivated_by',
                'archived_at',
                'archived_by',
                'status_reason',
            ]);
        });
    }
};
