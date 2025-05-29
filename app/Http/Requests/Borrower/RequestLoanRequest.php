<?php

namespace App\Http\Requests\Borrower;

use Illuminate\Foundation\Http\FormRequest;

class RequestLoanRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            //return request_loan
            'loan_amount' => ['required', 'numeric'],
            'loan_duration' => ['required', 'integer', 'between:1,12'],
            'loan_type' => ['required', 'string'],

            //income_information
            'employee_type' => ['required'],
            'position' => ['required'],
            'income' => ['required'],
            'bank_statement' => ['nullable'],




        ];
    }
}
