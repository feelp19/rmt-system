<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case EscrowDebit = 'escrow_debit';
    case EscrowReleaseCredit = 'escrow_release_credit';
    case DepositCredit = 'deposit_credit';
    case PixTopupCredit = 'pix_topup_credit';
    case BoostDebit = 'boost_debit';
}
