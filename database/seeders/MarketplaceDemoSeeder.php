<?php

namespace Database\Seeders;

use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Models\Listing;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Dados de demonstração para o MVP do marketplace.
 *
 * Idempotente (updateOrCreate por e-mail) — pode rodar várias vezes.
 * Senha de ambos os usuários demo: "password".
 */
class MarketplaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $alice = $this->demoUser('Alice (vendedora)', 'alice@demo.test', 50_000);
        $bob = $this->demoUser('Bob (comprador)', 'bob@demo.test', 500_00);

        $listings = [
            ['game' => 'Tibia', 'type' => ListingType::Gold, 'title' => '100kk Tibia Coins', 'quantity' => 100, 'price_cents' => 5_000, 'description' => 'Entrega imediata no servidor que preferir.'],
            ['game' => 'World of Warcraft', 'type' => ListingType::Gold, 'title' => '500k Gold WoW Retail', 'quantity' => 500, 'price_cents' => 12_000, 'description' => 'Gold via auction house.'],
            ['game' => 'Path of Exile', 'type' => ListingType::Item, 'title' => 'Mageblood Belt', 'quantity' => 1, 'price_cents' => 30_000, 'description' => 'Item raro, league Standard.'],
            ['game' => 'RuneScape', 'type' => ListingType::Item, 'title' => 'Twisted Bow', 'quantity' => 1, 'price_cents' => 25_000, 'description' => 'OSRS, entrega em Grand Exchange.'],
        ];

        foreach ($listings as $data) {
            Listing::updateOrCreate(
                ['seller_id' => $alice->id, 'title' => $data['title']],
                [...$data, 'status' => ListingStatus::Active],
            );
        }
    }

    private function demoUser(string $name, string $email, int $balanceCents): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make('password')],
        );

        Wallet::updateOrCreate(
            ['user_id' => $user->id],
            ['balance_cents' => $balanceCents],
        );

        return $user;
    }
}
