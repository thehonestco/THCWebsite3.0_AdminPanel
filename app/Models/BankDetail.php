<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_name',
        'account_holder_name',
        'account_number',
        'ifsc_code',
        'swift_code',
        'account_type'
    ];
}
