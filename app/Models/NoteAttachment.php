<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoteAttachment extends Model
{
    protected $fillable = [
        'note_id',
        'file_name',
        'file_path'
    ];

    public function note()
    {
        return $this->belongsTo(Note::class);
    }
}
