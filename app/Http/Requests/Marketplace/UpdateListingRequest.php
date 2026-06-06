<?php

namespace App\Http\Requests\Marketplace;

use App\Enums\ListingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateListingRequest extends FormRequest
{
    /** A posse do anúncio é checada no controller (escopo + Policy). */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'game' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(ListingType::class)],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'price_cents' => ['required', 'integer', 'min:1', 'max:100000000000'],
            // Na edição a foto é opcional (mantém a atual se não vier).
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }
}
