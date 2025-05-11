<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneOtp extends Model
{
    //fillable fields
    protected $fillable = [
        'phone',
        'otp',
        'expires_at'
    ];
}
