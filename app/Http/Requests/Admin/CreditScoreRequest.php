<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreditScoreRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            // return credit score
            'score' => ['required'],
            'status' => ['required'],
        ];
    }
}
