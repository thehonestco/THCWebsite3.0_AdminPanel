<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    protected $fillable = [
        'opportunity_id',
        'user_name',
        'title',
        'content',
        'note_status'
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
