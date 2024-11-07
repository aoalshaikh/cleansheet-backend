<?php

return [
    /*
     * If set to false, no activities will be saved to the database.
     */
    'enabled' => env('ACTIVITY_LOGGER_ENABLED', true),

    /*
     * When the clean-command is executed, all recording activities older than
     * the number of days specified here will be deleted.
     */
    'delete_records_older_than_days' => 60,

    /*
     * If no log name is passed to the activity() helper
     * we use this default log name.
     */
    'default_log_name' => 'default',

    /*
     * You can specify an auth driver here that gets user models.
     * If this is null we'll use the current Laravel auth driver.
     */
    'default_auth_driver' => null,

    /*
     * If set to true, the subject returns soft deleted models.
     */
    'subject_returns_soft_deleted_models' => true,

    /*
     * This model will be used to log activity.
     * It should implement the Spatie\Activitylog\Contracts\Activity interface
     * and extend Illuminate\Database\Eloquent\Model.
     */
    'activity_model' => \Spatie\Activitylog\Models\Activity::class,

    /*
     * This is the name of the table that will be created by the migration and
     * used by the Activity model shipped with this package.
     */
    'table_name' => 'activity_log',

    /*
     * This is the database connection that will be used by the migration and
     * the Activity model shipped with this package. In case it's not set
     * Laravel's database.default will be used instead.
     */
    'database_connection' => env('ACTIVITY_LOG_DB_CONNECTION'),

    /*
     * This is the name of the queue connection that will be used to queue jobs.
     */
    'queue_connection' => env('ACTIVITY_LOG_QUEUE_CONNECTION', 'sync'),

    /*
     * If you want to use a custom date format for the activity log,
     * you can specify it here.
     */
    'date_format' => 'Y-m-d H:i:s',

    /*
     * Configure here which events should be logged automatically.
     */
    'auto_log_events' => [
        'created' => true,
        'updated' => true,
        'deleted' => true,
        'restored' => true,
    ],

    /*
     * Configure here which attributes should be logged for specific events.
     */
    'auto_log_attributes' => [
        'created' => ['*'],
        'updated' => ['*'],
        'deleted' => ['*'],
        'restored' => ['*'],
    ],

    /*
     * Configure here which events should be ignored.
     */
    'ignored_events' => [
        'password',
        'remember_token',
    ],

    /*
     * Configure here which attributes should be ignored.
     */
    'ignored_attributes' => [
        'password',
        'remember_token',
    ],

    /*
     * When running the clean-command all recording activities older than
     * the number of days specified here will be deleted.
     */
    'activity_logger_clean_records_older_than_days' => 365,

    /*
     * Configure here which properties should be logged for each event.
     */
    'properties' => [
        'ip_address' => true,
        'user_agent' => true,
        'tenant_id' => true,
    ],

    /*
     * If you want to customize the creation of an activity,
     * you can do it here by registering your own closure.
     */
    'activity_logger_resolvers' => [
        'ip_address' => null,
        'user_agent' => null,
    ],

    /*
     * Configure here which events should be logged in the queue.
     */
    'queue_events' => [
        'created' => false,
        'updated' => false,
        'deleted' => false,
        'restored' => false,
    ],

    /*
     * Configure here which events should be logged with their full model.
     */
    'with_model_events' => [
        'created' => false,
        'updated' => false,
        'deleted' => false,
        'restored' => false,
    ],

    /*
     * When set to true, it will log activity to the specified log name
     * for the currently logged in user.
     */
    'submit_empty_logs' => false,

    /*
     * When set to true, it will automatically log activity for
     * the currently logged in user.
     */
    'auto_log_user' => true,
];
