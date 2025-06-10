<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectReasonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:255',
        ];
    }
}
