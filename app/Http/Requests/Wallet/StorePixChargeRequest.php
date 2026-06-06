<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class StorePixChargeRequest extends FormRequest
{
    /** A carga é sempre na carteira de auth() — requer usuário autenticado. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Valor a carregar em centavos (mín. R$5).
            'amount_cents' => ['required', 'integer', 'min:500', 'max:100000000000'],
        ];
    }
}
