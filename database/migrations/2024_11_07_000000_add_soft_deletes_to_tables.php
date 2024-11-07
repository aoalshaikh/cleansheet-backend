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
        // Add soft deletes to match_events table
        Schema::table('match_events', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to match_lineups table
        Schema::table('match_lineups', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to notification_logs table
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to notification_templates table
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to otps table
        Schema::table('otps', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to player_evaluations table
        Schema::table('player_evaluations', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to player_skills table
        Schema::table('player_skills', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to skills table
        Schema::table('skills', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to skill_categories table
        Schema::table('skill_categories', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to team_schedule_attendances table
        Schema::table('team_schedule_attendances', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove soft deletes from match_events table
        Schema::table('match_events', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from match_lineups table
        Schema::table('match_lineups', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from notification_logs table
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from notification_templates table
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from otps table
        Schema::table('otps', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from player_evaluations table
        Schema::table('player_evaluations', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from player_skills table
        Schema::table('player_skills', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from skills table
        Schema::table('skills', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from skill_categories table
        Schema::table('skill_categories', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from team_schedule_attendances table
        Schema::table('team_schedule_attendances', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
