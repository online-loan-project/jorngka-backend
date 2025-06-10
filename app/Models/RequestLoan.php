<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestLoan extends Model
{
    //
    protected $fillable = ['loan_amount', 'loan_duration', 'loan_type', 'rejection_reason', 'status', 'user_id'];
    //relationship to user
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    //relationship to nid_information
    public function nidInformation()
    {
        return $this->hasOne(NidInformation::class);
    }
    //relationship to income_information
    public function incomeInformation()
    {
        return $this->hasOne(IncomeInformation::class);
    }
}
