<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'position_id',
        'applicant_id',

        'experience_years',
        'current_ctc',
        'expected_ctc',
        'notice_period_days',

        'stage',
        'comment',
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
}
