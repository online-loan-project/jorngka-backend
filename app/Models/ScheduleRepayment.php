<?php

namespace App\Models;

use App\Constants\ConstLoanRepaymentStatus;
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

    //statusLabel
    public function statusLabel()
    {
        switch ($this->status) {
            case ConstLoanRepaymentStatus::PAID:
                return 'Paid';
            case ConstLoanRepaymentStatus::LATE:
                return 'Late';
            case ConstLoanRepaymentStatus::UNPAID:
                return 'Pending';
            default:
                return 'Unknown';
        }
    }
}
