<?php

namespace App\Enums;

enum StoreAssetType: string
{
    case Tool = 'tool';
    case Vehicle = 'vehicle';

    public function label(): string
    {
        return match ($this) {
            self::Tool => 'Tool',
            self::Vehicle => 'Vehicle',
        };
    }
}
