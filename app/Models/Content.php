<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Content extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'source_id', 'external_id', 'title', 'body', 'caption',
        'canonical_url', 'media_url', 'media_path', 'content_hash', 'media_type', 'published_at', 'metadata',
        'status', 'is_short', 'attempts', 'error_message', 'downloaded_at', 'sent_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'downloaded_at' => 'datetime',
        'sent_at' => 'datetime',
        'metadata' => 'array',
        'is_short' => 'boolean',
    ];

    public function source()
    {
        return $this->belongsTo(Source::class);
    }

    public function files()
    {
        return $this->hasMany(ContentFile::class);
    }
}
