<?php

namespace App\Enums;

enum WorkerPaymentType: string
{
    /** Money taken before Saturday. */
    case Advance = 'advance';

    /** The Saturday payout that closes the week. */
    case Settlement = 'settlement';

    public function label(): string
    {
        return match ($this) {
            self::Advance => 'Advance (before Saturday)',
            self::Settlement => 'Saturday settlement',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Advance => 'Advance',
            self::Settlement => 'Settlement',
        };
    }
}
