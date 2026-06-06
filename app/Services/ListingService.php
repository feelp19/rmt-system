<?php

namespace App\Services;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use DomainException;

class ListingService
{
    public function create(User $seller, array $data): Listing
    {
        return Listing::create([
            'seller_id' => $seller->id,
            'game' => $data['game'],
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'price_cents' => $data['price_cents'],
            'status' => ListingStatus::Active,
        ]);
    }

    public function cancel(Listing $listing): Listing
    {
        if ($listing->status !== ListingStatus::Active) {
            throw new DomainException('Apenas anúncios ativos podem ser cancelados.');
        }

        $listing->update(['status' => ListingStatus::Cancelled]);

        return $listing;
    }
}
