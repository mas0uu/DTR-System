<?php

use App\Models\User;
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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default(User::ROLE_EMPLOYEE)->after('is_admin');
            $table->index('role');
        });

        DB::table('users')
            ->where('is_admin', true)
            ->update(['role' => User::ROLE_ADMIN]);

        DB::table('users')
            ->where('is_admin', false)
            ->where('employee_type', 'intern')
            ->update(['role' => User::ROLE_INTERN]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
