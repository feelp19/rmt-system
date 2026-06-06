<?php

namespace App\Http\Requests\Marketplace;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) ?? false;
    }

    public function rules(): array
    {
        // Comprador e vendedor derivam de auth()/anúncio — nunca do request (regra 3).
        return [
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
        ];
    }
}
