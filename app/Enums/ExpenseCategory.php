<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Material = 'material';
    case Labour = 'labour';
    case Transport = 'transport';
    case Equipment = 'equipment';
    case Electricity = 'electricity';
    case Water = 'water';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Material => 'Material',
            self::Labour => 'Labour',
            self::Transport => 'Transport',
            self::Equipment => 'Equipment',
            self::Electricity => 'Electricity',
            self::Water => 'Water',
            self::Other => 'Other',
        };
    }

    /**
     * @return list<self>
     */
    public static function manualCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $category) => $category !== self::Material,
        ));
    }
}
