<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'lead_code',
        'company_name',
        'tagline',
        'name',
        'email',
        'phone',
        'source',
        'stage'
    ];

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }
}
