<?php

namespace App\Traits;

use App\Services\Logging\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;

trait LogsActivity
{
    /**
     * Boot the trait.
     */
    protected static function bootLogsActivity(): void
    {
        if (!config('activitylog.enabled', true)) {
            return;
        }

        static::created(function (Model $model) {
            if ($model->shouldLogActivity()) {
                $model->logActivity('created');
            }
        });

        static::updated(function (Model $model) {
            if ($model->shouldLogActivity() && $model->wasChanged()) {
                $model->logActivity('updated', [
                    'old' => $model->getOriginal(),
                    'attributes' => $model->getAttributes(),
                ]);
            }
        });

        static::deleted(function (Model $model) {
            if ($model->shouldLogActivity()) {
                $model->logActivity('deleted');
            }
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                if ($model->shouldLogActivity()) {
                    $model->logActivity('restored');
                }
            });
        }
    }

    /**
     * Log an activity for this model.
     */
    public function logActivity(string $description, array $properties = []): void
    {
        // Add tenant context if available
        if (method_exists($this, 'tenant') && $this->tenant) {
            $properties['tenant_id'] = $this->tenant->id;
        } elseif (property_exists($this, 'tenant_id') && $this->tenant_id) {
            $properties['tenant_id'] = $this->tenant_id;
        }

        // Add model specific properties
        if (method_exists($this, 'getActivityLogProperties')) {
            $properties = array_merge(
                $properties,
                $this->getActivityLogProperties()
            );
        }

        // Filter out ignored attributes
        if (property_exists($this, 'activityLogIgnored')) {
            $properties = $this->filterIgnoredProperties($properties);
        }

        // Get the logger instance
        $logger = App::make(ActivityLogger::class);

        // Log the activity with the appropriate log name
        $logger->log(
            $this->getActivityLogDescription($description),
            $this,
            $properties,
            $this->getActivityLogName()
        );
    }

    /**
     * Get the description for the activity log.
     */
    protected function getActivityLogDescription(string $description): string
    {
        if (property_exists($this, 'activityLogDescriptions') && 
            isset($this->activityLogDescriptions[$description])) {
            return $this->activityLogDescriptions[$description];
        }

        $modelName = class_basename($this);
        return "{$description} {$modelName}";
    }

    /**
     * Filter out ignored properties.
     */
    protected function filterIgnoredProperties(array $properties): array
    {
        if (!isset($properties['attributes']) && !isset($properties['old'])) {
            return $properties;
        }

        foreach ($this->activityLogIgnored as $ignored) {
            if (isset($properties['attributes'])) {
                unset($properties['attributes'][$ignored]);
            }
            if (isset($properties['old'])) {
                unset($properties['old'][$ignored]);
            }
        }

        return $properties;
    }

    /**
     * Get the log name for the model.
     */
    public function getActivityLogName(): string
    {
        if (property_exists($this, 'activityLogName')) {
            return $this->activityLogName;
        }

        return strtolower(class_basename($this));
    }

    /**
     * Determine if activity logging is enabled for this model.
     */
    public function shouldLogActivity(): bool
    {
        if (method_exists($this, 'isActivityLoggingEnabled')) {
            return $this->isActivityLoggingEnabled();
        }

        return true;
    }

    /**
     * Get the causer of the activity.
     */
    public function getCauserForActivityLog(): ?Model
    {
        if (method_exists($this, 'getActivityLogCauser')) {
            return $this->getActivityLogCauser();
        }

        return Auth::user();
    }

    /**
     * Get the event name for the activity log.
     */
    protected function getActivityLogEventName(string $event): string
    {
        if (property_exists($this, 'activityLogEvents') && 
            isset($this->activityLogEvents[$event])) {
            return $this->activityLogEvents[$event];
        }

        return $event;
    }

    /**
     * Get the options for activity logging.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getActivityLogAttributes())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the attributes to log.
     */
    protected function getActivityLogAttributes(): array
    {
        if (property_exists($this, 'activityLogAttributes')) {
            return $this->activityLogAttributes;
        }

        return ['*'];
    }
}
