<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    // app/Models/UserDevice.php

    protected $fillable = [
        'user_id', // nullable for unauthenticated devices
        'device_id',
        'device_name',
        'device_type',
        'os',
        'ip_address',
        'fcm_token',
        'last_active_at',
        'first_seen_at'
    ];

    protected $dates = [
        'last_active_at',
        'first_seen_at'
    ];

    protected static function booted()
    {
        static::creating(function ($device) {
            $device->first_seen_at = now();
        });
    }
}
