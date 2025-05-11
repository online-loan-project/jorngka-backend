<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'image',
        'user_id'
    ];
    //relation to users table
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
