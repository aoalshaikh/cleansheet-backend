<?php

namespace App\Services\Notification;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

class NotificationService extends BaseService
{
    /**
     * Send a notification using a template.
     */
    public function sendFromTemplate(string $templateSlug, User $user, array $data = []): array
    {
        $template = NotificationTemplate::where('slug', $templateSlug)
            ->where('is_active', true)
            ->firstOrFail();

        $compiled = $template->compile($data);
        $results = [];

        foreach ($template->channels as $channel) {
            if (!$user->hasNotificationChannel($channel)) {
                continue;
            }

            $log = $this->createLog($template, $user, $channel, $compiled);
            
            try {
                $this->send($channel, $user, $compiled['title'], $compiled['content']);
                $log->markAsSent();
                $results[$channel] = true;
            } catch (\Exception $e) {
                $log->markAsFailed($e->getMessage());
                $results[$channel] = false;
            }
        }

        return $results;
    }

    /**
     * Send a direct notification without a template.
     */
    public function sendDirect(User $user, string $channel, string $title, string $content): bool
    {
        if (!$user->hasNotificationChannel($channel)) {
            return false;
        }

        $log = $this->createLog(null, $user, $channel, [
            'title' => $title,
            'content' => $content,
        ]);

        try {
            $this->send($channel, $user, $title, $content);
            $log->markAsSent();
            return true;
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to multiple users.
     */
    public function sendBulk(array $userIds, string $templateSlug, array $data = []): array
    {
        $template = NotificationTemplate::where('slug', $templateSlug)
            ->where('is_active', true)
            ->firstOrFail();

        $compiled = $template->compile($data);
        $results = [];

        User::whereIn('id', $userIds)->chunk(100, function ($users) use ($template, $compiled, &$results) {
            foreach ($users as $user) {
                $userResults = [];
                foreach ($template->channels as $channel) {
                    if (!$user->hasNotificationChannel($channel)) {
                        continue;
                    }

                    $log = $this->createLog($template, $user, $channel, $compiled);

                    try {
                        $this->send($channel, $user, $compiled['title'], $compiled['content']);
                        $log->markAsSent();
                        $userResults[$channel] = true;
                    } catch (\Exception $e) {
                        $log->markAsFailed($e->getMessage());
                        $userResults[$channel] = false;
                    }
                }
                $results[$user->id] = $userResults;
            }
        });

        return $results;
    }

    /**
     * Get user's notification history.
     */
    public function getUserNotifications(User $user, array $filters = []): mixed
    {
        $query = NotificationLog::where('user_id', $user->id);

        if (isset($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (isset($filters['status'])) {
            if ($filters['status'] === 'read') {
                $query->whereNotNull('read_at');
            } elseif ($filters['status'] === 'unread') {
                $query->whereNull('read_at');
            }
        }

        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Mark notifications as read.
     */
    public function markAsRead(User $user, array $notificationIds = []): int
    {
        $query = NotificationLog::where('user_id', $user->id)
            ->whereNull('read_at');

        if (!empty($notificationIds)) {
            $query->whereIn('id', $notificationIds);
        }

        return $query->update(['read_at' => now()]);
    }

    /**
     * Clear notifications.
     */
    public function clearNotifications(User $user, ?string $status = null, ?string $beforeDate = null): int
    {
        $query = NotificationLog::where('user_id', $user->id);

        if ($status === 'read') {
            $query->whereNotNull('read_at');
        }

        if ($beforeDate) {
            $query->where('created_at', '<=', $beforeDate);
        }

        return $query->delete();
    }

    /**
     * Create a notification log entry.
     */
    protected function createLog(?NotificationTemplate $template, User $user, string $channel, array $compiled): NotificationLog
    {
        return NotificationLog::create([
            'template_id' => $template?->id,
            'user_id' => $user->id,
            'channel' => $channel,
            'title' => $compiled['title'],
            'content' => $compiled['content'],
        ]);
    }

    /**
     * Send the actual notification.
     */
    protected function send(string $channel, User $user, string $title, string $content): void
    {
        // Implementation will vary based on channel
        match ($channel) {
            'email' => $this->sendEmail($user, $title, $content),
            'sms' => $this->sendSms($user, $content),
            'push' => $this->sendPushNotification($user, $title, $content),
            default => throw new \InvalidArgumentException("Unsupported channel: {$channel}"),
        };
    }

    /**
     * Send email notification.
     */
    protected function sendEmail(User $user, string $title, string $content): void
    {
        // Implement email sending logic
    }

    /**
     * Send SMS notification.
     */
    protected function sendSms(User $user, string $content): void
    {
        // Implement SMS sending logic
    }

    /**
     * Send push notification.
     */
    protected function sendPushNotification(User $user, string $title, string $content): void
    {
        // Implement push notification logic
    }
}
