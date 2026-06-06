<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tier' => $this->tier->value,
            'tier_label' => $this->tier->label(),
            'price_cents' => $this->price_cents,
            'payment_method' => $this->payment_method->value,
            'status' => $this->status->value,
            'starts_at' => $this->starts_at,
            'expires_at' => $this->expires_at,
            'listing' => ListingResource::make($this->whenLoaded('listing')),
            'created_at' => $this->created_at,
        ];
    }
}
