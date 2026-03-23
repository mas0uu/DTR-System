<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('holidays')
            ->where('holiday_type', 'company')
            ->update(['holiday_type' => 'special']);

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `holidays` MODIFY `holiday_type` ENUM('regular','special') NOT NULL DEFAULT 'regular'");
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
    WHERE t.relname = 'holidays'
      AND c.contype = 'c'
      AND pg_get_constraintdef(c.oid) ILIKE '%holiday_type%'
  LOOP
    EXECUTE format('ALTER TABLE holidays DROP CONSTRAINT %I', constraint_name);
  END LOOP;

  ALTER TABLE holidays
    ADD CONSTRAINT holidays_holiday_type_check
    CHECK (holiday_type IN ('regular', 'special'));
END $$;
SQL);
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `holidays` MODIFY `holiday_type` ENUM('regular','special','company') NOT NULL DEFAULT 'regular'");
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
    WHERE t.relname = 'holidays'
      AND c.contype = 'c'
      AND pg_get_constraintdef(c.oid) ILIKE '%holiday_type%'
  LOOP
    EXECUTE format('ALTER TABLE holidays DROP CONSTRAINT %I', constraint_name);
  END LOOP;

  ALTER TABLE holidays
    ADD CONSTRAINT holidays_holiday_type_check
    CHECK (holiday_type IN ('regular', 'special', 'company'));
END $$;
SQL);
        }
    }
};
