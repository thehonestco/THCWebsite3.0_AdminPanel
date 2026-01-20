<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadBusinessDetail extends Model
{
    protected $fillable = [
        'lead_id',
        'business_name',
        'gst_number',
        'pan_number',
        'address',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
