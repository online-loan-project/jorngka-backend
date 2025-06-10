<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomeInformation extends Model
{
    //
    protected $fillable = ['employee_type', 'position', 'income', 'bank_statement', 'request_loan_id'];
    //relationship to request_loan
    public function requestLoan()
    {
        return $this->belongsTo(RequestLoan::class);
    }
}
