<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'direction' => $this->direction->value,
            'amount_cents' => $this->amount_cents,
            'balance_after_cents' => $this->balance_after_cents,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'seq' => $this->seq,
            'code' => $this->hash, // código de confiabilidade (HMAC — seguro de exibir)
            'created_at' => $this->created_at,
        ];
    }
}
