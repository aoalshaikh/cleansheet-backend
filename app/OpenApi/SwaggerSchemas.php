<?php

namespace App\OpenApi;

/**
 * 
 * @OA\Schema(
 *     schema="Organization",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(
 *         property="settings",
 *         type="object",
 *         @OA\Property(
 *             property="notifications",
 *             type="object",
 *             additionalProperties=@OA\Schema(type="boolean")
 *         ),
 *         @OA\Property(
 *             property="features",
 *             type="object",
 *             additionalProperties=@OA\Schema(type="boolean")
 *         ),
 *         @OA\Property(
 *             property="subscription",
 *             type="object",
 *             @OA\Property(property="player_price", type="number"),
 *             @OA\Property(property="player_duration", type="integer"),
 *             @OA\Property(property="currency", type="string")
 *         )
 *     ),
 *     @OA\Property(property="teams", type="array", @OA\Items(ref="#/components/schemas/Team")),
 *     @OA\Property(property="subscriptions", type="array", @OA\Items(ref="#/components/schemas/OrganizationSubscription"))
 * )
 * 
 * @OA\Schema(
 *     schema="SubscriptionPlan",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="price", type="number", format="float"),
 *     @OA\Property(property="currency", type="string"),
 *     @OA\Property(property="interval", type="string", enum={"monthly", "yearly"}),
 *     @OA\Property(property="features", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="limits", type="object"),
 *     @OA\Property(property="is_active", type="boolean")
 * )
 * 
 * @OA\Schema(
 *     schema="OrganizationSubscription",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="organization", ref="#/components/schemas/Organization"),
 *     @OA\Property(property="plan", ref="#/components/schemas/SubscriptionPlan"),
 *     @OA\Property(property="status", type="string", enum={"active", "cancelled", "expired"}),
 *     @OA\Property(property="started_at", type="string", format="date-time"),
 *     @OA\Property(property="expires_at", type="string", format="date-time"),
 *     @OA\Property(property="cancelled_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="cancellation_reason", type="string", nullable=true)
 * )
 * 
 * @OA\Schema(
 *     schema="PlayerSubscription",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="player", ref="#/components/schemas/User"),
 *     @OA\Property(property="organization", ref="#/components/schemas/Organization"),
 *     @OA\Property(property="status", type="string", enum={"active", "cancelled", "expired"}),
 *     @OA\Property(property="amount", type="number", format="float"),
 *     @OA\Property(property="currency", type="string"),
 *     @OA\Property(property="started_at", type="string", format="date-time"),
 *     @OA\Property(property="expires_at", type="string", format="date-time"),
 *     @OA\Property(property="cancelled_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="cancellation_reason", type="string", nullable=true)
 * )
 * 
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="email", type="string", format="email"),
 *     @OA\Property(property="phone", type="string"),
 *     @OA\Property(property="avatar_url", type="string", nullable=true),
 *     @OA\Property(
 *         property="roles",
 *         type="array",
 *         @OA\Items(type="string", enum={"player", "coach", "admin"})
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="MatchLineup",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="match_id", type="integer"),
 *     @OA\Property(property="player", ref="#/components/schemas/User"),
 *     @OA\Property(property="position", type="string"),
 *     @OA\Property(property="is_starter", type="boolean"),
 *     @OA\Property(property="jersey_number", type="integer"),
 *     @OA\Property(property="minutes_played", type="integer")
 * )
 * 
 * @OA\Schema(
 *     schema="MatchEvent",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="match_id", type="integer"),
 *     @OA\Property(property="player", ref="#/components/schemas/User"),
 *     @OA\Property(property="type", type="string", enum={"goal", "assist", "yellow_card", "red_card", "substitution"}),
 *     @OA\Property(property="minute", type="integer"),
 *     @OA\Property(property="details", type="object", nullable=true)
 * )
 * 
 * @OA\Schema(
 *     schema="Team",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="coach", ref="#/components/schemas/User"),
 *     @OA\Property(property="tiers", type="array", @OA\Items(ref="#/components/schemas/TeamTier")),
 *     @OA\Property(property="players", type="array", @OA\Items(ref="#/components/schemas/User")),
 *     @OA\Property(
 *         property="practice_schedule",
 *         type="object",
 *         @OA\Property(
 *             property="days",
 *             type="array",
 *             @OA\Items(type="string", enum={"monday","tuesday","wednesday","thursday","friday","saturday","sunday"})
 *         ),
 *         @OA\Property(property="time", type="string", format="HH:mm"),
 *         @OA\Property(property="duration", type="integer", minimum=30, maximum=240)
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="TeamTier",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="team_id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="level", type="integer"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(
 *         property="requirements",
 *         type="object",
 *         @OA\Property(property="min_age", type="integer"),
 *         @OA\Property(property="max_age", type="integer"),
 *         @OA\Property(
 *             property="skill_levels",
 *             type="object",
 *             additionalProperties=@OA\Schema(type="integer", minimum=0, maximum=100)
 *         )
 *     ),
 *     @OA\Property(property="team", ref="#/components/schemas/Team"),
 *     @OA\Property(
 *         property="players",
 *         type="array",
 *         @OA\Items(
 *             @OA\Schema(
 *                 @OA\Property(property="player", ref="#/components/schemas/User"),
 *                 @OA\Property(property="joined_at", type="string", format="date-time"),
 *                 @OA\Property(property="left_at", type="string", format="date-time", nullable=true)
 *             )
 *         )
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="Notification",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="type", type="string"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(property="channel", type="string", enum={"email", "sms", "push"}),
 *     @OA\Property(property="status", type="string", enum={"read", "unread"}),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="read_at", type="string", format="date-time", nullable=true)
 * )
 * 
 * @OA\Schema(
 *     schema="NotificationPreferences",
 *     type="object",
 *     @OA\Property(
 *         property="channels",
 *         type="object",
 *         additionalProperties=@OA\Schema(type="boolean")
 *     ),
 *     @OA\Property(
 *         property="types",
 *         type="object",
 *         additionalProperties=@OA\Schema(type="boolean")
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="PlayerProfile",
 *     type="object",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/User"),
 *         @OA\Schema(
 *             type="object",
 *             @OA\Property(
 *                 property="teams",
 *                 type="array",
 *                 @OA\Items(ref="#/components/schemas/Team")
 *             ),
 *             @OA\Property(
 *                 property="preferences",
 *                 type="object",
 *                 @OA\Property(
 *                     property="notifications",
 *                     ref="#/components/schemas/NotificationPreferences"
 *                 ),
 *                 @OA\Property(
 *                     property="privacy",
 *                     type="object"
 *                 )
 *             )
 *         )
 *     }
 * )
 * 
 * @OA\Schema(
 *     schema="PlayerStats",
 *     type="object",
 *     @OA\Property(property="matches_played", type="integer"),
 *     @OA\Property(property="minutes_played", type="integer"),
 *     @OA\Property(property="goals_scored", type="integer"),
 *     @OA\Property(property="assists", type="integer"),
 *     @OA\Property(property="yellow_cards", type="integer"),
 *     @OA\Property(property="red_cards", type="integer"),
 *     @OA\Property(property="attendance_rate", type="number", format="float"),
 *     @OA\Property(
 *         property="skill_progress",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/PlayerSkill")
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="PlayerSkill",
 *     type="object",
 *     @OA\Property(property="skill", ref="#/components/schemas/Skill"),
 *     @OA\Property(property="current_level", type="integer"),
 *     @OA\Property(property="target_level", type="integer"),
 *     @OA\Property(property="target_date", type="string", format="date")
 * )
 * 
 * @OA\Schema(
 *     schema="Skill",
 *     type="object",
 *     @OA\Property(property="id", type="string"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="category", ref="#/components/schemas/SkillCategory")
 * )
 * 
 * @OA\Schema(
 *     schema="SkillCategory",
 *     type="object",
 *     @OA\Property(property="id", type="string"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="description", type="string")
 * )
 * 
 * @OA\Schema(
 *     schema="PlayerAttendance",
 *     type="object",
 *     @OA\Property(property="schedule", ref="#/components/schemas/TeamSchedule"),
 *     @OA\Property(property="status", type="string", enum={"present", "absent", "late", "excused"}),
 *     @OA\Property(property="notes", type="string", nullable=true),
 *     @OA\Property(property="recorded_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="TeamSchedule",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="team_id", type="integer"),
 *     @OA\Property(property="type", type="string", enum={"practice", "match", "event"}),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="scheduled_at", type="string", format="date-time"),
 *     @OA\Property(property="duration", type="integer", minimum=30, maximum=240),
 *     @OA\Property(property="location", type="string", nullable=true),
 *     @OA\Property(property="is_mandatory", type="boolean"),
 *     @OA\Property(property="team", ref="#/components/schemas/Team"),
 *     @OA\Property(
 *         property="attendance",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/TeamScheduleAttendance")
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="PlayerEvaluation",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="player", ref="#/components/schemas/User"),
 *     @OA\Property(property="evaluator", ref="#/components/schemas/User"),
 *     @OA\Property(property="team", ref="#/components/schemas/Team"),
 *     @OA\Property(property="evaluation_date", type="string", format="date"),
 *     @OA\Property(
 *         property="ratings",
 *         type="object",
 *         @OA\Property(property="technical", type="integer", minimum=1, maximum=10),
 *         @OA\Property(property="tactical", type="integer", minimum=1, maximum=10),
 *         @OA\Property(property="physical", type="integer", minimum=1, maximum=10),
 *         @OA\Property(property="mental", type="integer", minimum=1, maximum=10)
 *     ),
 *     @OA\Property(property="notes", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="TeamStats",
 *     type="object",
 *     @OA\Property(property="matches_played", type="integer"),
 *     @OA\Property(property="matches_won", type="integer"),
 *     @OA\Property(property="matches_drawn", type="integer"),
 *     @OA\Property(property="matches_lost", type="integer"),
 *     @OA\Property(property="goals_scored", type="integer"),
 *     @OA\Property(property="goals_conceded", type="integer"),
 *     @OA\Property(property="clean_sheets", type="integer"),
 *     @OA\Property(property="win_rate", type="number", format="float"),
 *     @OA\Property(
 *         property="top_scorers",
 *         type="array",
 *         @OA\Items(
 *             @OA\Schema(
 *                 @OA\Property(property="player", ref="#/components/schemas/User"),
 *                 @OA\Property(property="goals", type="integer")
 *             )
 *         )
 *     ),
 *     @OA\Property(
 *         property="top_assists",
 *         type="array",
 *         @OA\Items(
 *             @OA\Schema(
 *                 @OA\Property(property="player", ref="#/components/schemas/User"),
 *                 @OA\Property(property="assists", type="integer")
 *             )
 *         )
 *     ),
 *     @OA\Property(property="average_attendance_rate", type="number", format="float")
 * )
 * 
 * @OA\Schema(
 *     schema="TeamScheduleAttendance",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="schedule_id", type="integer"),
 *     @OA\Property(property="player", ref="#/components/schemas/User"),
 *     @OA\Property(property="status", type="string", enum={"present", "absent", "late", "excused"}),
 *     @OA\Property(property="notes", type="string", nullable=true),
 *     @OA\Property(property="recorded_at", type="string", format="date-time"),
 *     @OA\Property(property="recorded_by", ref="#/components/schemas/User"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="GameMatch",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="home_team_id", type="integer"),
 *     @OA\Property(property="away_team_id", type="integer"),
 *     @OA\Property(property="scheduled_at", type="string", format="date-time"),
 *     @OA\Property(property="venue", type="string"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="status", type="string", enum={"scheduled", "in_progress", "completed", "cancelled"}),
 *     @OA\Property(property="started_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="completed_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="cancelled_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="cancellation_reason", type="string", nullable=true),
 *     @OA\Property(
 *         property="score",
 *         type="object",
 *         @OA\Property(property="home", type="integer"),
 *         @OA\Property(property="away", type="integer")
 *     ),
 *     @OA\Property(property="home_team", ref="#/components/schemas/Team"),
 *     @OA\Property(property="away_team", ref="#/components/schemas/Team"),
 *     @OA\Property(
 *         property="lineup",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/MatchLineup")
 *     ),
 *     @OA\Property(
 *         property="events",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/MatchEvent")
 *     ),
 *     @OA\Property(property="settings", type="object", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 */