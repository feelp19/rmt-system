<?php

return [
    /*
     | Segredo do HMAC do ledger de confiabilidade. String aleatória longa.
     | Gerar: php -r "echo bin2hex(random_bytes(32));"
     | NUNCA versionar o valor real — só em .env (gitignored).
     | Rotacionar invalida a verificação das linhas antigas (limitação conhecida).
     */
    'hmac_key' => env('LEDGER_HMAC_KEY'),
];
