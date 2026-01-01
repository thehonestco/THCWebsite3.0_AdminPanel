<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = ['section_id','name','slug','sort_order'];

    public function section() { return $this->belongsTo(Section::class); }
    public function permissions() { return $this->hasMany(Permission::class); }
}
