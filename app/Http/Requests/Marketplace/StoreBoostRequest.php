<?php

namespace App\Http\Requests\Marketplace;

use App\Enums\BoostTier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBoostRequest extends FormRequest
{
    /** A posse do anúncio é checada no controller (escopo + Policy). */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'tier' => ['required', Rule::enum(BoostTier::class)],
            // Inc 1: somente carteira. PIX entra no Inc 2 (PushinPay).
            'payment_method' => ['required', Rule::in(['wallet'])],
        ];
    }
}
