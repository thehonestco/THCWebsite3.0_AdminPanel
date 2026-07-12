<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaAsset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'media_code',
        'original_name',
        'title',
        'media_type',
        'status',
        'disk',
        'directory',
        'file_name',
        'path',
        'url',
        'source_extension',
        'source_mime_type',
        'converted_extension',
        'converted_mime_type',
        'size_bytes',
        'width',
        'height',
        'duration_seconds',
        'processing_status',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'duration_seconds' => 'decimal:2',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
