<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class NotificationTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'title',
        'content',
        'channels',
        'variables',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'channels' => 'array',
        'variables' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    const CHANNEL_SMS = 'sms';
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_PUSH = 'push';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($template) {
            if (empty($template->slug)) {
                $template->slug = Str::slug($template->name);
            }
        });
    }

    public function logs()
    {
        return $this->hasMany(NotificationLog::class, 'template_id');
    }

    public function compile(array $data)
    {
        $title = $this->title;
        $content = $this->content;

        foreach ($data as $key => $value) {
            $title = str_replace("{{$key}}", $value, $title);
            $content = str_replace("{{$key}}", $value, $content);
        }

        return [
            'title' => $title,
            'content' => $content,
        ];
    }

    public function hasChannel($channel)
    {
        return in_array($channel, $this->channels);
    }

    public function addChannel($channel)
    {
        if (!$this->hasChannel($channel)) {
            $this->channels = array_merge($this->channels, [$channel]);
            $this->save();
        }
    }

    public function removeChannel($channel)
    {
        if ($this->hasChannel($channel)) {
            $this->channels = array_diff($this->channels, [$channel]);
            $this->save();
        }
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByChannel($query, $channel)
    {
        return $query->where('channels', 'like', "%{$channel}%");
    }

    public function scopeBySlug($query, $slug)
    {
        return $query->where('slug', $slug);
    }
}
