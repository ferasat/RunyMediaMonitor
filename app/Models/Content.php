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
        'canonical_url', 'content_hash', 'media_type', 'published_at', 'metadata',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function source()
    {
        return $this->belongsTo(Source::class);
    }
}