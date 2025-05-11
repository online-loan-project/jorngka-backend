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

            //nid_information
            'nid_number' => ['required', 'numeric'],
            'nid_image' => ['nullable'],

            //income_information
            'employee_type' => ['required'],
            'position' => ['required'],
            'income' => ['required'],
            'bank_statement' => ['nullable'],




        ];
    }
}
