<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InterestRate;
use Illuminate\Http\Request;

class InterestRateController extends Controller
{
    // Interest rate list
    public function index()
    {
        $interestRate = InterestRate::query()->latest()->first();
        return $this->success($interestRate);
    }
    // Interest rate create
    public function create(Request $request)
    {
        $request->validate([
            'rate' => 'required|numeric|min:0|max:100',
        ]);
        $interestRate = InterestRate::create([
            'rate' => $request->input('rate'),
        ]);
        return $this->success($interestRate);
    }
}
