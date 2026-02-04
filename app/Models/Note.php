<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    protected $fillable = [
        'opportunity_id',
        'comment',
        'created_by', // optional (user id / name)
    ];

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function attachments()
    {
        return $this->hasMany(NoteAttachment::class);
    }
}
