<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'template_id',
        'user_id',
        'channel',
        'title',
        'content',
        'metadata',
        'sent_at',
        'read_at',
        'failed_at',
        'failure_reason',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(NotificationTemplate::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markAsSent()
    {
        $this->update([
            'sent_at' => now(),
            'failed_at' => null,
            'failure_reason' => null,
        ]);
    }

    public function markAsFailed($reason)
    {
        $this->update([
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);
    }

    public function markAsRead()
    {
        $this->update(['read_at' => now()]);
    }

    public function isSent()
    {
        return !is_null($this->sent_at);
    }

    public function isRead()
    {
        return !is_null($this->read_at);
    }

    public function hasFailed()
    {
        return !is_null($this->failed_at);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeSent($query)
    {
        return $query->whereNotNull('sent_at');
    }

    public function scopeFailed($query)
    {
        return $query->whereNotNull('failed_at');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeInDateRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }
}
