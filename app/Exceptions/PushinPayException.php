<?php

namespace App\Exceptions;

use RuntimeException;

/** Falha de comunicação/contrato com a PushinPay. Traduzida em 503 no controller. */
class PushinPayException extends RuntimeException
{
}
