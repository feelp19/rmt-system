<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class DepositRequest extends FormRequest
{
    /** Requer usuário autenticado (a carteira sempre é a de auth()). */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Valor do top-up em centavos inteiros.
            'amount_cents' => ['required', 'integer', 'min:1', 'max:100000000000'],
        ];
    }
}
