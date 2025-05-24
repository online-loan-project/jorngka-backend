<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditScore extends Model
{
    //fillable fields for credit score
    protected $fillable = ['user_id', 'score', 'status'];
    //relationship to user
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
