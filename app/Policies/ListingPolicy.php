<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    /** Qualquer usuário autenticado pode anunciar. */
    public function create(User $user): bool
    {
        return true;
    }

    /** Apenas o dono do anúncio pode cancelá-lo. */
    public function delete(User $user, Listing $listing): bool
    {
        return $listing->seller_id === $user->id;
    }

    /** Apenas o dono do anúncio pode turbiná-lo (boost). */
    public function boost(User $user, Listing $listing): bool
    {
        return $listing->seller_id === $user->id;
    }
}
