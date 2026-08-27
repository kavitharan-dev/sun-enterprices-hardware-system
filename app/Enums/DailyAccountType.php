<?php

namespace App\Enums;

enum DailyAccountType: string
{
    case Sale = 'sale';
    case Purchase = 'purchase';
    case WorkerAdvance = 'worker_advance';
    case WorkerSettlement = 'worker_settlement';
    case OwnerPayment = 'owner_payment';
    case ProjectExpense = 'project_expense';
    case OtherIncome = 'other_income';
    case OtherExpense = 'other_expense';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Hardware sale — money IN',
            self::Purchase => 'Stock purchase — money OUT',
            self::WorkerAdvance => 'Worker advance — money OUT',
            self::WorkerSettlement => 'Worker salary — money OUT',
            self::OwnerPayment => 'Site owner payment — money IN',
            self::ProjectExpense => 'Site expense — money OUT',
            self::OtherIncome => 'Other income — money IN',
            self::OtherExpense => 'Other expense — money OUT',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Sale => 'Hardware sale',
            self::Purchase => 'Stock purchase',
            self::WorkerAdvance => 'Worker advance',
            self::WorkerSettlement => 'Worker salary',
            self::OwnerPayment => 'Site owner payment',
            self::ProjectExpense => 'Site expense',
            self::OtherIncome => 'Other income',
            self::OtherExpense => 'Other expense',
        };
    }

    public function directionLabel(): string
    {
        return $this->isIncome() ? 'Money in' : 'Money out';
    }

    public function isIncome(): bool
    {
        return in_array($this, [self::Sale, self::OwnerPayment, self::OtherIncome], true);
    }
}
