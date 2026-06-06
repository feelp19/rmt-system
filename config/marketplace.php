<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Taxa da plataforma
    |--------------------------------------------------------------------------
    |
    | Percentual cobrado sobre o valor de cada transação concluída. A taxa é
    | retida pela plataforma no momento da liberação do escrow (release).
    | Cálculo sempre em centavos inteiros — ver App\Services\OrderService.
    |
    */
    'fee_percent' => (int) env('MARKETPLACE_FEE_PERCENT', 5),

    /*
    |--------------------------------------------------------------------------
    | Boost (destaque pago)
    |--------------------------------------------------------------------------
    |
    | Dias que um boost fica ativo após o pagamento (igual para todos os tiers;
    | o tier altera só posição/alcance — ver App\Enums\BoostTier).
    |
    */
    'boost_days' => (int) env('MARKETPLACE_BOOST_DAYS', 7),
];
