<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('workspace_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->restrictOnDelete();
            $table->index('workspace_id');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE users ADD CONSTRAINT users_role_workspace_check CHECK (
                    (system_role = 'admin' AND workspace_id IS NULL)
                    OR (system_role = 'employee' AND workspace_id IS NOT NULL)
                )
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_workspace_check');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_id');
        });
    }
};
