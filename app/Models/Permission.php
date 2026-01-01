<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['module_id','action_id','name'];

    public function module() { return $this->belongsTo(Module::class); }
    public function action() { return $this->belongsTo(Action::class); }

    public function roles() {
        return $this->belongsToMany(Role::class, 'role_permission')->withTimestamps();
    }
}
