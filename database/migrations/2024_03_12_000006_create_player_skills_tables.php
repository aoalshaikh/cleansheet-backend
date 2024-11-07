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
        // Define available skills and their categories
        Schema::create('skill_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('max_points')->default(100);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // Define specific skills within categories
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('skill_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('max_points')->default(100);
            $table->json('criteria')->nullable(); // Evaluation criteria
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('category_id');
        });

        // Track player skill levels
        Schema::create('player_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->integer('current_level')->default(0);
            $table->integer('target_level')->nullable();
            $table->date('target_date')->nullable();
            $table->json('progress_history')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'skill_id']);
            $table->index(['user_id', 'current_level']);
        });

        // Daily skill evaluations
        Schema::create('player_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluated_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->date('evaluation_date');
            $table->json('skill_scores'); // Array of skill_id => score
            $table->integer('total_points');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'evaluation_date']);
            $table->index(['team_id', 'evaluation_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_evaluations');
        Schema::dropIfExists('player_skills');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('skill_categories');
    }
};
