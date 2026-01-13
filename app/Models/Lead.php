<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'lead_code',
        'company_name',
        'company_website',
        'company_linkedin',
        'tagline',
        'tags',
        'city',
        'country',
        'poc_name',
        'poc_email',
        'poc_phone',
        'poc_linkedin',
        'source',
        'stage',
        'is_converted',
        'converted_at',
        'client_id',
    ];

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }
}
