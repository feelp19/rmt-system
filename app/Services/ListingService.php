<?php

namespace App\Services;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;

class ListingService
{
    public function __construct(
        private readonly ImageUploadService $images,
        private readonly XpService $xp,
    ) {}

    public function create(User $seller, array $data, UploadedFile $photo): Listing
    {
        // XP de "primeiro anúncio" é concedido uma única vez.
        $isFirstListing = ! $seller->listings()->exists();

        $photoPath = $this->images->storeImage($photo, 'listings');

        $listing = Listing::create([
            'seller_id' => $seller->id,
            'game' => $data['game'],
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'price_cents' => $data['price_cents'],
            'status' => ListingStatus::Active,
            'photo_path' => $photoPath,
        ]);

        if ($isFirstListing) {
            $this->xp->award($seller->id, XpService::FIRST_LISTING);
        }

        return $listing;
    }

    /** Edita um anúncio próprio (só enquanto ativo). Troca a foto se vier uma nova. */
    public function update(Listing $listing, array $data, ?UploadedFile $photo): Listing
    {
        if ($listing->status !== ListingStatus::Active) {
            throw new DomainException('Só é possível editar anúncios ativos.');
        }

        $attributes = [
            'game' => $data['game'],
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'price_cents' => $data['price_cents'],
        ];

        if ($photo !== null) {
            $oldPath = $listing->photo_path;
            $attributes['photo_path'] = $this->images->storeImage($photo, 'listings');
            $listing->update($attributes);
            $this->images->delete($oldPath); // remove a antiga só depois de salvar a nova
        } else {
            $listing->update($attributes);
        }

        return $listing;
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
