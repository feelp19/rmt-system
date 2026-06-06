<?php

namespace App\Http\Resources\Marketplace;

use App\Http\Resources\User\PublicUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'game' => $this->game,
            'type' => $this->type->value,
            'title' => $this->title,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'price_cents' => $this->price_cents,
            'status' => $this->status->value,
            // URL relativa (mesma origem) — endpoint público que faz stream do arquivo.
            'photo_url' => $this->photo_path ? '/api/listings/'.$this->id.'/photo' : null,
            'seller' => PublicUserResource::make($this->whenLoaded('seller')),
            // Boost ativo (badge/destaque) — null quando não está turbinado.
            'boost' => $this->whenLoaded('activeBoost', fn () => $this->activeBoost ? [
                'tier' => $this->activeBoost->tier->value,
                'tier_label' => $this->activeBoost->tier->label(),
                'expires_at' => $this->activeBoost->expires_at,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
