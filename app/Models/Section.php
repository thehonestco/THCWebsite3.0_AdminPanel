<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $fillable = ['name','slug','sort_order'];

    public function modules() {
        return $this->hasMany(Module::class)->orderBy('sort_order');
    }
}
