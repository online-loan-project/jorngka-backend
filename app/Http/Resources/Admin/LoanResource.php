<?php

namespace App\Http\Resources\Admin;

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
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'email' => $this?->user?->email ?? null,
            'phone' => $this?->user?->phone ?? null,
            'name' => $this?->user?->borrower?->first_name . ' ' . $this?->user?->borrower?->last_name ?? null,
            'image' => $this?->user?->borrower?->image ?? null,

            'user' => [
                'id' => $this?->user?->id,
                'email' => $this?->user?->email,
                'name' => $this?->user?->borrower?->first_name . ' ' . $this?->user?->borrower?->last_name ?? null,
                'phone' => $this?->user?->phone,
                'borrower_id' => $this?->user?->borrower?->id,
            ],

            'schedule_repayments' => $this?->scheduleRepayment->map(function ($repayment) {
                return [
                    'id' => $repayment->id,
                    'repayment_date' => $repayment?->repayment_date,
                    'emi_amount' => $repayment?->emi_amount,
                    'status' => $repayment?->status,
                    'paid_date' => $repayment?->paid_date,
                ];
            })->toArray(),
        ];
    }
}
