<?php

namespace App\Services\Logging;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class ActivityLogger
{
    protected Request $request;
    protected array $properties = [];
    protected ?string $logName = null;
    protected ?Model $subject = null;
    protected ?Model $causer = null;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function log(string $description, Model $subject, array $properties = [], ?string $logName = null): Activity
    {
        $properties = array_merge(
            $properties,
            $this->properties ?? [],
            $this->getRequestMetadata(),
            $this->getTenantContext($subject)
        );

        $activity = activity($logName ?? $this->logName)
            ->performedOn($this->subject ?? $subject)
            ->causedBy($this->causer ?? $this->getCauser($subject))
            ->withProperties($properties);

        // Reset instance properties after logging
        $this->reset();

        return $activity->log($description);
    }

    public function logAuth(string $action, Model $subject, bool $success): Activity
    {
        $description = $success ? $action : "failed {$action}";

        return $this->log($description, $subject, [
            'success' => $success,
        ], 'auth');
    }

    public function logOtpVerification(Model $subject, string $type, bool $success): Activity
    {
        $description = $success ? 'verified OTP' : 'failed OTP verification';

        return $this->log($description, $subject, [
            'success' => $success,
            'type' => $type,
        ], 'auth');
    }

    public function logProfileUpdate(Model $subject, array $oldValues, array $newValues): Activity
    {
        return $this->log('updated profile', $subject, [
            'old' => $oldValues,
            'attributes' => $newValues,
        ], 'profile');
    }

    protected function getRequestMetadata(): array
    {
        return [
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ];
    }

    protected function getTenantContext(Model $subject): array
    {
        if (method_exists($subject, 'tenant') && $subject->tenant) {
            return ['tenant_id' => $subject->tenant->id];
        }

        if (property_exists($subject, 'tenant_id') && $subject->tenant_id) {
            return ['tenant_id' => $subject->tenant_id];
        }

        return [];
    }

    protected function getCauser(Model $subject): ?Model
    {
        // First check if the subject has a specific causer defined
        if (method_exists($subject, 'getCauserForActivityLog')) {
            $causer = $subject->getCauserForActivityLog();
            if ($causer instanceof Model) {
                return $causer;
            }
        }

        // Then check for authenticated user
        $user = Auth::user();
        if ($user instanceof Model) {
            return $user;
        }

        // Finally check if the subject itself should be the causer
        if (method_exists($subject, 'shouldBeCauser') && $subject->shouldBeCauser()) {
            return $subject;
        }

        return null;
    }

    public function withProperties(array $properties): self
    {
        $this->properties = array_merge($this->properties, $properties);
        return $this;
    }

    public function withProperty(string $key, $value): self
    {
        $this->properties[$key] = $value;
        return $this;
    }

    public function tap(callable $callback): self
    {
        $callback($this);
        return $this;
    }

    public function createdBy(Model $causer): self
    {
        $this->causer = $causer;
        return $this;
    }

    public function on(Model $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function useLog(string $logName): self
    {
        $this->logName = $logName;
        return $this;
    }

    protected function reset(): void
    {
        $this->properties = [];
        $this->logName = null;
        $this->subject = null;
        $this->causer = null;
    }
}
