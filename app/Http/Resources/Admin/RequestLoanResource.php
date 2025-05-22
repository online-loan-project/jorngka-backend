<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestLoanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, // Maps to "id": 1 from JSON
            'name' => ($this->user?->borrower?->first_name ?? '') . ' ' . ($this->user?->borrower?->last_name ?? ''),
            // Combines "CHI" + " " + "THARY" from user.borrower to create "CHI THARY"
            'phone' => $this->user?->phone ?? null, // Maps to "092772440" from user.phone
            'loan_amount' => $this->loan_amount, // Maps to "600.00"
            'loan_duration' => $this->loan_duration, // Maps to 6
            'status' => $this->status, // Maps to "rejected"
            'rejection_reason' => $this->rejection_reason, // Maps to "Not eligible"
            'created_at' => $this->created_at, // Maps to "2025-04-29T07:40:59.000000Z"
            'updated_at' => $this->updated_at, // Maps to "2025-05-02T10:34:15.000000Z"

            'nid_information' => [
                'nid_number' => $this->nidInformation?->nid_number, // Maps to "101412140"
                'nid_image' => $this->nidInformation?->nid_image, // Maps to URL
                'nid_back_image' => $this->nidInformation?->nid_back_image, // Not present in JSON (would be null)
            ],

            'income_information' => [
                'employee_type' => $this->incomeInformation?->employee_type, // Maps to "employer"
                'position' => $this->incomeInformation?->position, // Maps to "Server Side"
                'income' => $this->incomeInformation?->income, // Maps to "600.00"
                'bank_statement' => $this->incomeInformation?->bank_statement, // Maps to URL
            ],
        ];
    }
}
