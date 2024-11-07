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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opponent_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('opponent_name')->nullable(); // For external teams not in our system
            $table->string('venue');
            $table->dateTime('scheduled_at');
            $table->enum('type', ['friendly', 'league', 'cup', 'tournament']);
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->integer('home_score')->nullable();
            $table->integer('away_score')->nullable();
            $table->json('metadata')->nullable(); // For additional match details
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'scheduled_at']);
            $table->index('status');
        });

        // Match events like goals, cards, substitutions
        Schema::create('match_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['goal', 'assist', 'yellow_card', 'red_card', 'substitution', 'injury', 'other']);
            $table->integer('minute');
            $table->json('metadata')->nullable(); // Additional event details
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['match_id', 'type']);
        });

        // Match lineups
        Schema::create('match_lineups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['starting', 'substitute', 'not_selected'])->default('not_selected');
            $table->string('position')->nullable();
            $table->integer('jersey_number')->nullable();
            $table->json('statistics')->nullable(); // Player stats for the match
            $table->timestamps();

            $table->unique(['match_id', 'player_id']);
            $table->index(['match_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_lineups');
        Schema::dropIfExists('match_events');
        Schema::dropIfExists('matches');
    }
};
