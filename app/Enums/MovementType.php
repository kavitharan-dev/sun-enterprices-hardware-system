<?php

namespace App\Enums;

enum MovementType: string
{
    case PurchaseIn = 'purchase_in';
    case SaleOut = 'sale_out';
    case MaterialIssueOut = 'material_issue_out';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case ReturnIn = 'return_in';
    case ReturnOut = 'return_out';
    case DamagedOut = 'damaged_out';
    case OpeningBalance = 'opening_balance';

    public function label(): string
    {
        return match ($this) {
            self::PurchaseIn => 'Purchase',
            self::SaleOut => 'Sale',
            self::MaterialIssueOut => 'Material Issue',
            self::AdjustmentIn => 'Adjustment (In)',
            self::AdjustmentOut => 'Adjustment (Out)',
            self::ReturnIn => 'Return (In)',
            self::ReturnOut => 'Return (Out)',
            self::DamagedOut => 'Damaged',
            self::OpeningBalance => 'Opening Balance',
        };
    }

    public function isInbound(): bool
    {
        return in_array($this, [
            self::PurchaseIn,
            self::AdjustmentIn,
            self::ReturnIn,
            self::OpeningBalance,
        ], true);
    }
}
