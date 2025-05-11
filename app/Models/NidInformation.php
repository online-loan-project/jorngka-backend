<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NidInformation extends Model
{
    //
    protected $fillable = ['nid_number', 'nid_image', 'status', 'request_loan_id'];
    //relationship to request_loan
    public function requestLoan()
    {
        return $this->belongsTo(RequestLoan::class);
    }
}
