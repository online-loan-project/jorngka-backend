<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleRepayment extends Model
{

    protected $fillable = [
        'repayment_date',
        'emi_amount',
        'status',
        'paid_date',
        'loan_id'
    ];

    //relationship to loan
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
