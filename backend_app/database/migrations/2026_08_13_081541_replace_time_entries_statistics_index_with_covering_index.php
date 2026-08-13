<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX time_entries_timesheet_id_work_date_index');
        DB::statement(
            'CREATE INDEX time_entries_timesheet_id_work_date_index
                ON time_entries (timesheet_id, work_date)
                INCLUDE (hours, is_overtime)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX time_entries_timesheet_id_work_date_index');
        DB::statement(
            'CREATE INDEX time_entries_timesheet_id_work_date_index ON time_entries (timesheet_id, work_date)'
        );
    }
};
