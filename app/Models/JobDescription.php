<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobDescription extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'status',
        'about_job',
        'key_skills',
        'responsibilities',
        'created_by'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function positions()
    {
        return $this->hasMany(Position::class);
    }
}
