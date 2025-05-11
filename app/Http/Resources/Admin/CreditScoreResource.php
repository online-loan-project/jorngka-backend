<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditScoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->user?->borrower?->first_name . ' ' . $this->user?->borrower?->last_name,
            'phone' => $this->user?->phone,
            'score' => $this->score,
            'status' => (int) $this->status,
            'last_update' => $this->updated_at,
        ];
    }
}
