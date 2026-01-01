<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\User;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\JobDescription;

class Position extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'job_description_id',
        'organization_name',
        'source',

        'job_type',
        'work_mode',

        'experience_min',
        'experience_max',
        'salary_min',
        'salary_max',

        'reference_code',

        'location',
        'website_url',
        'linkedin_url',
        'tags',
        'contact_name',
        'contact_email',
        'contact_phone',

        'status',
        'created_by',
    ];

    protected $casts = [
        'experience_min' => 'integer',
        'experience_max' => 'integer',
        'salary_min'     => 'integer',
        'salary_max'     => 'integer',
    ];

    public function jobDescription()
    {
        return $this->belongsTo(JobDescription::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Applications (1 position → many applications)
     */
    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    /**
     * Applicants through applications
     */
    public function applicants()
    {
        return $this->belongsToMany(Applicant::class, 'applications')
            ->withPivot([
                'stage',
                'comment',
                'experience_years',
                'current_ctc',
                'expected_ctc',
                'notice_period_days',
                'last_contact_at',
                'created_by',
            ])
            ->withTimestamps();
    }
}
