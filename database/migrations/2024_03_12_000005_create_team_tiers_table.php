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
        Schema::create('team_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_tier_id')->nullable()->constrained('team_tiers')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('level')->default(0); // Hierarchy level (0 = top level)
            $table->integer('min_age')->nullable();
            $table->integer('max_age')->nullable();
            $table->json('requirements')->nullable(); // Skill requirements for this tier
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'level']);
            $table->index('is_active');
        });

        // Player assignments to tiers
        Schema::create('team_tier_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_tier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('evaluation')->nullable(); // Player's evaluation for this tier
            $table->dateTime('promoted_at')->nullable();
            $table->dateTime('demoted_at')->nullable();
            $table->timestamps();

            $table->unique(['team_tier_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_tier_players');
        Schema::dropIfExists('team_tiers');
    }
};
