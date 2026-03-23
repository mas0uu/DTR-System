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

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE dtr_rows
                MODIFY status ENUM('draft', 'in_progress', 'finished', 'leave', 'missed') NOT NULL DEFAULT 'draft'
            ");
        }

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
DO $$
DECLARE constraint_name text;
BEGIN
  FOR constraint_name IN
    SELECT c.conname
    FROM pg_constraint c
    JOIN pg_class t ON t.oid = c.conrelid
    WHERE t.relname = 'dtr_rows'
      AND c.contype = 'c'
      AND pg_get_constraintdef(c.oid) ILIKE '%status%'
  LOOP
    EXECUTE format('ALTER TABLE dtr_rows DROP CONSTRAINT %I', constraint_name);
  END LOOP;

  ALTER TABLE dtr_rows
    ADD CONSTRAINT dtr_rows_status_check
    CHECK (status IN ('draft', 'in_progress', 'finished', 'leave', 'missed'));
END $$;
SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE dtr_rows
                MODIFY status ENUM('draft', 'finished') NOT NULL DEFAULT 'draft'
            ");
        }

        if ($driver === 'pgsql') {
            DB::table('dtr_rows')
                ->whereNotIn('status', ['draft', 'finished'])
                ->update(['status' => 'draft']);

            DB::unprepared(<<<'SQL'
DO $$
DECLARE constraint_name text;
BEGIN
  FOR constraint_name IN
    SELECT c.conname
    FROM pg_constraint c
    JOIN pg_class t ON t.oid = c.conrelid
    WHERE t.relname = 'dtr_rows'
      AND c.contype = 'c'
      AND pg_get_constraintdef(c.oid) ILIKE '%status%'
  LOOP
    EXECUTE format('ALTER TABLE dtr_rows DROP CONSTRAINT %I', constraint_name);
  END LOOP;

  ALTER TABLE dtr_rows
    ADD CONSTRAINT dtr_rows_status_check
    CHECK (status IN ('draft', 'finished'));
END $$;
SQL);
        }

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
