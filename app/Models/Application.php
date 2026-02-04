<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    use SoftDeletes;

    public const STAGE_FRESH       = 'fresh';
    public const STAGE_SCREENING   = 'screening';
    public const STAGE_HR_ROUND    = 'hr_round';
    public const STAGE_TECH_ROUND  = 'tech_round';
    public const STAGE_FINAL_ROUND = 'final_round';
    public const STAGE_OFFER_SENT  = 'offer_sent';
    public const STAGE_REJECTED    = 'rejected';
    public const STAGE_DROPPED     = 'dropped';

    public const ACTIVE_STAGES = [
        self::STAGE_FRESH,
        self::STAGE_SCREENING,
        self::STAGE_HR_ROUND,
        self::STAGE_TECH_ROUND,
        self::STAGE_FINAL_ROUND,
        self::STAGE_OFFER_SENT,
    ];

    protected $fillable = [
        'position_id',
        'applicant_id',

        'experience_years',
        'current_ctc',
        'expected_ctc',
        'notice_period_days',

        'stage',
        'comment',
        'resume_path',
        'last_contact_at',

        'created_by',
    ];

    protected $casts = [
        'experience_years' => 'decimal:1',
        'current_ctc' => 'decimal:2',
        'expected_ctc' => 'decimal:2',
        'notice_period_days' => 'integer',
        'last_contact_at' => 'datetime',
    ];

    /**
     * Applicant
     */
    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    /**
     * Position
     */
    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Recruiter / creator
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('stage', self::ACTIVE_STAGES);
    }
}
