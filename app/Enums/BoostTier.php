<?php

namespace App\Enums;

enum BoostTier: string
{
    case Basic = 'basic';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';

    /** Preço do pacote em centavos inteiros. */
    public function priceCents(): int
    {
        return match ($this) {
            self::Basic => 500,
            self::Intermediate => 1500,
            self::Advanced => 2500,
        };
    }

    /** Peso de ordenação (maior = aparece antes). Denormalizado na coluna `weight`. */
    public function weight(): int
    {
        return match ($this) {
            self::Basic => 1,
            self::Intermediate => 2,
            self::Advanced => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Basic => 'Básico',
            self::Intermediate => 'Intermediário',
            self::Advanced => 'Avançado',
        };
    }

    /** Além da faixa "Em destaque", flutua pro topo da vitrine geral (intermediário+). */
    public function floatsInGrid(): bool
    {
        return $this !== self::Basic;
    }
}
