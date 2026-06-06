<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resultado da verificação de confiabilidade de uma linha do ledger.
 * Envolve a LedgerEntry + o booleano `valid` já calculado pelo LedgerService
 * (a Resource é só apresentação — nenhuma lógica de verificação aqui).
 */
class LedgerVerificationResource extends JsonResource
{
    public function __construct($resource, private readonly bool $valid)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'valid' => $this->valid,
            'type' => $this->type->value,
            'direction' => $this->direction->value,
            'amount_cents' => $this->amount_cents,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'created_at' => $this->created_at,
        ];
    }
}
