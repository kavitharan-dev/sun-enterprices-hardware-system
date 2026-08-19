<?php

namespace App\Enums;

enum CashierRequestType: string
{
    case SalePayment = 'sale_payment';
    case PurchasePayment = 'purchase_payment';
    case WorkerAdvance = 'worker_advance';
    case WorkerSettlement = 'worker_settlement';
    case OwnerPayment = 'owner_payment';
    case ProjectExpense = 'project_expense';

    public function label(): string
    {
        return match ($this) {
            self::SalePayment => 'Hardware sale payment',
            self::PurchasePayment => 'Stock purchase payment',
            self::WorkerAdvance => 'Worker advance',
            self::WorkerSettlement => 'Worker salary',
            self::OwnerPayment => 'Site owner payment',
            self::ProjectExpense => 'Site expense',
        };
    }

    public function direction(): string
    {
        return match ($this) {
            self::SalePayment, self::OwnerPayment => 'income',
            default => 'expense',
        };
    }
}
