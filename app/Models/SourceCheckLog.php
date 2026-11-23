<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SourceCheckLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_id', 'status', 'items_discovered', 'items_new',
        'error_message', 'metadata', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'items_discovered' => 'integer',
        'items_new' => 'integer',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function source()
    {
        return $this->belongsTo(Source::class);
    }
}