<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class ContentFile extends Model
{
    protected $fillable = ['content_id', 'token', 'path', 'name', 'kind', 'size', 'width', 'height',
        'duration', 'delivery', 'expires_at', 'deleted_from_disk_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'deleted_from_disk_at' => 'datetime',
        'size' => 'integer',
    ];

    public function content()
    {
        return $this->belongsTo(Content::class);
    }

    public function absolutePath(): string
    {
        return rtrim(config('rmm.media_root'), '/').'/'.$this->path;
    }

    public function isAvailable(): bool
    {
        return ! $this->deleted_from_disk_at && (! $this->expires_at || $this->expires_at->isFuture())
            && is_file($this->absolutePath());
    }

    public function downloadUrl(): string
    {
        $expires = $this->expires_at ?? now()->addDays(config('rmm.link_days'));
        return URL::temporarySignedRoute('media.download', $expires, ['token' => $this->token, 'name' => $this->name]);
    }

    public function sizeMb(): float
    {
        return round($this->size / 1048576, 1);
    }
}
