<?php

use App\Enums\ProjectRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('project_role', array_column(ProjectRole::cases(), 'value'));
            $table->smallInteger('approval_rank');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->index(['user_id', 'active']);
            $table->index(['project_id', 'active', 'approval_rank']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE project_members ADD CONSTRAINT project_members_role_rank_check CHECK (
                    (project_role = 'participant' AND approval_rank = 0)
                    OR (project_role = 'senior' AND approval_rank = 1)
                    OR (project_role = 'manager' AND approval_rank = 2)
                    OR (project_role = 'project_lead' AND approval_rank = 3)
                )
                SQL
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_members');
    }
};
