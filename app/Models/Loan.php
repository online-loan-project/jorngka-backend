<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    //

    protected $fillable = [
        'loan_duration',
        'loan_repayment',
        'revenue',
        'status',
        'user_id',
        'request_loan_id',
        'credit_score_id',
        'interest_rate_id'
    ];
    //relationship to user
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    //relationship to request_loan
    public function requestLoan()
    {
        return $this->belongsTo(RequestLoan::class);
    }
    //relationship to credit_score
    public function creditScore()
    {
        return $this->belongsTo(CreditScore::class);
    }
    //relationship to interest_rate
    public function interestRate()
    {
        return $this->belongsTo(InterestRate::class);
    }
    //relationship to schedule_repayment
    public function scheduleRepayment()
    {
        return $this->hasMany(ScheduleRepayment::class);
    }

    //User belongs to user
    public function borrower()
    {
        return $this->belongsTo(Borrower::class);
    }

}
