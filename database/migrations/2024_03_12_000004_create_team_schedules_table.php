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
        Schema::create('team_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->enum('type', ['practice', 'meeting', 'fitness', 'other'])->default('practice');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('is_recurring')->default(false);
            $table->json('recurrence_pattern')->nullable(); // For recurring events (weekly practice, etc.)
            $table->json('metadata')->nullable();
            $table->boolean('notify_team')->default(true);
            $table->boolean('is_cancelled')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'starts_at']);
            $table->index('type');
        });

        // Attendance tracking for scheduled events
        Schema::create('team_schedule_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('absent');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['team_schedule_id', 'user_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_schedule_attendances');
        Schema::dropIfExists('team_schedules');
    }
};
