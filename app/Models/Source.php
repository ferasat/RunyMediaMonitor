<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Source extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type', 'name', 'url', 'external_id', 'username', 'handle',
        'description', 'is_active', 'check_interval_minutes',
        'last_checked_at', 'last_successful_check_at', 'last_error',
        'configuration', 'credentials', 'media_settings', 'notification_settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'check_interval_minutes' => 'integer',
        'last_checked_at' => 'datetime',
        'last_successful_check_at' => 'datetime',
        'configuration' => 'array',
        'credentials' => 'array',
        'media_settings' => 'array',
        'notification_settings' => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isDue(): bool
    {
        if (! $this->last_checked_at) {
            return true;
        }
        return $this->last_checked_at->addMinutes($this->check_interval_minutes)->isPast();
    }
}