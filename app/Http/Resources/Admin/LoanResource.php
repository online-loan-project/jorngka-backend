<?php

namespace App\Http\Resources\Admin;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'revenue' => $this->revenue,
            'loan_duration' => $this->loan_duration,
            'loan_repayment' => $this->loan_repayment,
            'status' => (int)$this->status,
            'created_at' => Carbon::parse($this->created_at)->format('Y-m-d H:i:s'),
            'email' => $this?->user?->email ?? null,
            'phone' => $this?->user?->phone ?? null,
            'name' => $this?->user?->borrower?->first_name . ' ' . $this?->user?->borrower?->last_name ?? null,
            'image' => $this?->user?->borrower?->image ?? null,

            'user' => [
                'email' => $this?->user?->email,
                'name' => $this?->user?->borrower?->first_name . ' ' . $this?->user?->borrower?->last_name ?? null,
                'phone' => $this?->user?->phone,
            ],

            'schedule_repayments' => $this?->scheduleRepayment->map(function ($repayment) {
                return [
                    'id' => $repayment->id,
                    'repayment_date' => Carbon::parse($repayment?->repayment_date)->format('Y-m-d H:i:s'),
                    'emi_amount' => $repayment?->emi_amount,
                    'status' => (int)$repayment?->status,
                    'paid_date' => Carbon::parse($repayment?->paid_date)->format('Y-m-d H:i:s'),
                ];
            })->toArray(),
        ];
    }
}
