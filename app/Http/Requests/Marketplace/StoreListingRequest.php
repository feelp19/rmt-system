<?php

namespace App\Http\Requests\Marketplace;

use App\Enums\ListingType;
use App\Models\Listing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Listing::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'game' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(ListingType::class)],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000000'],
            // Preço total do anúncio em centavos (mín. 1 centavo).
            'price_cents' => ['required', 'integer', 'min:1', 'max:100000000000'],
            // Foto obrigatória (camada 1 do hardening) — finfo + reprocessamento no Service.
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }
}
