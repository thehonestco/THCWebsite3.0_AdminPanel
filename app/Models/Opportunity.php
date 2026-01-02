<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    protected $fillable = [
        'lead_id',
        'title',
        'description',
        'amount',
        'owner_name',
        'stage',
        'status'
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }
}
