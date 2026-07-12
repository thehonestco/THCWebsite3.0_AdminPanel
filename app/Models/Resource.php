<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resource extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'resource_type',
        'sub_industry',
        'sub_service',
        'listing_title',
        'listing_description',
        'listing_image_url',
        'listing_image_media_id',
        'status',
        'resource_payload',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'resource_payload' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function listingImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'listing_image_media_id');
    }
}
