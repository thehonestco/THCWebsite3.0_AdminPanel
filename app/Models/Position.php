<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Position extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'position_name',
        'job_description_id',
        'job_type',
        'work_mode',
        'city',
        'country',
        'skills',
        'experience_min',
        'experience_max',
        'salary_min',
        'salary_max',
        'status',
        'created_by',
    ];

    protected $casts = [
        'skills' => 'array',
    ];

    /**
     * Job Description
     */
    public function jobDescription()
    {
        return $this->belongsTo(JobDescription::class);
    }

    /**
     * Applications under this position
     */
    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    /**
     * Creator (Recruiter / Admin)
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
