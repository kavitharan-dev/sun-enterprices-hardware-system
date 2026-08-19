<?php

namespace App\Models\Concerns;

use App\Models\DailyAccountEntry;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasFinancialTransaction
{
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(DailyAccountEntry::class, 'daily_account_entry_id');
    }

    public function transactionNo(): ?string
    {
        return $this->financialTransaction?->transaction_no;
    }
}
