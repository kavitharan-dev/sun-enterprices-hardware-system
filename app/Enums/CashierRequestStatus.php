<?php

namespace App\Enums;

enum CashierRequestStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting cashier',
            self::Confirmed => 'Confirmed',
            self::Rejected => 'Rejected',
        };
    }
}
