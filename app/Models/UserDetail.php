<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDetail extends Model
{
    protected $fillable = [
        'user_id',
        'phone',
        'designation',
        'department',
        'linkedin_url',
        'tags'
    ];
}
