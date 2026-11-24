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
        'configuration', 'credentials', 'media_settings', 'notification_settings', 'baselined_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'check_interval_minutes' => 'integer',
        'last_checked_at' => 'datetime',
        'last_successful_check_at' => 'datetime',
        'baselined_at' => 'datetime',
        'configuration' => 'array',
        'credentials' => 'array',
        'media_settings' => 'array',
        'notification_settings' => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public const MANUAL_URL_PREFIX = 'manual://';

    /** Hidden per-platform source that owns manual (pasted-link) downloads. */
    public static function manualFor(string $type): self
    {
        return static::withTrashed()->firstOrCreate(['url' => self::MANUAL_URL_PREFIX.$type], [
            'type' => $type, 'name' => 'دانلود دستی', 'is_active' => false,
            'baselined_at' => now(), 'configuration' => ['manual' => true],
        ]);
    }

    public function isManual(): bool
    {
        return str_starts_with((string) $this->url, self::MANUAL_URL_PREFIX);
    }

    public function scopeMonitored($query)
    {
        return $query->where(fn ($q) => $q->whereNull('url')->orWhere('url', 'not like', self::MANUAL_URL_PREFIX.'%'));
    }

    public function contents()
    {
        return $this->hasMany(Content::class);
    }

    /** Detect source type + a display name from a pasted URL. */
    public static function detect(string $url): array
    {
        $u = trim($url);
        if (preg_match('~(youtube\.com|youtu\.be)/(@[\w.\-]+|channel/[\w\-]+|c/[\w.\-]+|user/[\w.\-]+)~i', $u, $m)) {
            return ['type' => 'youtube', 'name' => ltrim(basename($m[2]), '@'), 'handle' => $m[2]];
        }
        if (preg_match('~instagram\.com/([A-Za-z0-9_.]+)~i', $u, $m) && ! in_array(strtolower($m[1]), ['p', 'reel', 'reels', 'stories', 'explore'])) {
            return ['type' => 'instagram', 'name' => $m[1], 'username' => $m[1]];
        }
        return ['type' => 'website', 'name' => parse_url($u, PHP_URL_HOST) ?: $u];
    }

    public function isDue(): bool
    {
        if (! $this->last_checked_at) {
            return true;
        }
        return $this->last_checked_at->addMinutes($this->check_interval_minutes)->isPast();
    }
}