<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timesheet_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->text('description')->nullable();
            $table->decimal('hours', 5, 2);
            $table->boolean('is_overtime')->default(false);
            $table->timestamps();

            $table->index(['timesheet_id', 'work_date']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE time_entries ADD CONSTRAINT time_entries_hours_check CHECK (hours > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
