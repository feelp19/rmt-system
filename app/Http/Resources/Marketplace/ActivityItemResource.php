<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Uma venda concluída para o feed público da home.
 * NUNCA expõe dado do comprador (privacidade). Vendedor e preço já são
 * públicos na vitrine.
 *
 * @mixin \App\Models\Order
 */
class ActivityItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->listing->type->value,
            'game' => $this->listing->game,
            'title' => $this->listing->title,
            'amount_cents' => $this->amount_cents,
            'completed_at' => $this->completed_at,
            'seller_name' => $this->seller->name,
        ];
    }
}
