<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobDescription extends Model
{
    use SoftDeletes;

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'title',
        'status',
        'about_job',
        'key_skills',
        'responsibilities',
        'interview_process',
        'created_by',
    ];

    /**
     * Auto set created_by on create
     */
    protected static function booted(): void
    {
        static::creating(function (self $job) {
            if (auth()->check() && empty($job->created_by)) {
                $job->created_by = auth()->id();
            }
        });
    }

    /**
     * Job created by user
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Job positions linked to description
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }
}
