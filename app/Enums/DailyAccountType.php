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

    public function isIncome(): bool
    {
        return in_array($this, [self::Sale, self::OwnerPayment, self::OtherIncome], true);
    }
}
