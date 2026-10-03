<?php

namespace App\Services\Finance;

use App\Models\Transaction;

interface FinanceSyncService
{
    /** @return array{status: string, error: ?string} status: synced|failed */
    public function postSale(Transaction $trx): array;

    /** @return array{status: string, error: ?string} status: synced|failed */
    public function postReversal(Transaction $trx): array;
}
