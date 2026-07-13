<?php

use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tin')->unique();
//            $table->foreignIdFor(Plan::class)->nullable()->constrained()->nullOnDelete();
//            $table->string('subscription_status')->default('free');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
