<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_comment')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign(['project_id', 'workspace_id'], 'timesheets_project_workspace_foreign')
                ->references(['id', 'workspace_id'])
                ->on('projects')
                ->restrictOnDelete();
            $table->unique(['project_id', 'user_id', 'period_start', 'period_end']);
            $table->index(['user_id', 'status', 'period_start']);
            $table->index(['project_id', 'status', 'period_start']);
            $table->index('reviewed_by_user_id');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE timesheets ADD CONSTRAINT timesheets_period_check CHECK (period_start <= period_end)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheets');
    }
};
