<?php

namespace App\Http\Resources\Marketplace;

use App\Http\Resources\User\PublicUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'amount_cents' => $this->amount_cents,
            'fee_cents' => $this->fee_cents,
            'seller_payout_cents' => $this->seller_payout_cents,
            'seller_confirmed_at' => $this->seller_confirmed_at,
            'buyer_confirmed_at' => $this->buyer_confirmed_at,
            'completed_at' => $this->completed_at,
            'listing' => ListingResource::make($this->whenLoaded('listing')),
            'buyer' => PublicUserResource::make($this->whenLoaded('buyer')),
            'seller' => PublicUserResource::make($this->whenLoaded('seller')),
            'created_at' => $this->created_at,
        ];
    }
}
