<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Applicant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'linkedin_url',
        'skills',
        'status',
        'created_by',
    ];

    /**
     * Creator (recruiter/admin)
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Applications (1 applicant → many applications)
     */
    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    /**
     * Positions applied for (via applications)
     */
    public function positions()
    {
        return $this->belongsToMany(Position::class, 'applications')
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
