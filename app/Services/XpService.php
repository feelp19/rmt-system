<?php

namespace App\Services;

use App\Models\User;

/**
 * XP / níveis / perks. XP é ganho por uso da plataforma (vender, comprar,
 * turbinar, primeiro anúncio). O nível dá reputação (badge), perk (desconto na
 * taxa) e posição no ranking.
 */
class XpService
{
    // Quanto cada ação concede.
    public const SELL = 50;
    public const BUY = 20;
    public const BOOST = 15;
    public const FIRST_LISTING = 10;

    /** XP mínimo de cada nível (índice 0 = Nv1). */
    private const THRESHOLDS = [0, 100, 300, 600, 1000, 1500, 2100, 2800, 3600, 4500];

    /** Concede XP de forma atômica (increment numa linha). */
    public function award(int $userId, int $amount): void
    {
        if ($amount > 0) {
            User::whereKey($userId)->increment('xp', $amount);
        }
    }

    public static function levelForXp(int $xp): int
    {
        $level = 1;
        foreach (self::THRESHOLDS as $index => $threshold) {
            if ($xp >= $threshold) {
                $level = $index + 1;
            }
        }

        return $level;
    }

    /** Dados de progresso pro frontend (barra de XP). */
    public static function progress(int $xp): array
    {
        $level = self::levelForXp($xp);

        return [
            'level' => $level,
            'xp' => $xp,
            'level_floor' => self::THRESHOLDS[$level - 1] ?? 0,
            'next_level_xp' => self::THRESHOLDS[$level] ?? null, // null = nível máximo
        ];
    }

    /**
     * Perk: taxa da plataforma (em basis points) pro nível do vendedor.
     * Base 5% (500 bps), -0,2% por nível acima do 1, piso de 3% (300 bps).
     */
    public static function feeBpsForLevel(int $level, int $baseBps): int
    {
        return max(300, $baseBps - 20 * ($level - 1));
    }
}
