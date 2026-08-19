<?php

namespace App\Support;

use Illuminate\Support\Collection;

class ItemLineSummary
{
    /**
     * @param  iterable<int, object>  $items
     */
    public static function from(iterable $items, string $quantityAttribute, int $limit = 6): string
    {
        $parts = Collection::make($items)
            ->map(function (object $item) use ($quantityAttribute) {
                $name = $item->product?->name ?: 'Unknown product';
                $qty = self::formatQty((float) ($item->{$quantityAttribute} ?? 0));
                $unit = $item->product?->unit?->symbol;

                return $unit ? "{$name} × {$qty} {$unit}" : "{$name} × {$qty}";
            })
            ->filter()
            ->values();

        if ($parts->isEmpty()) {
            return '';
        }

        $shown = $parts->take($limit);
        $extra = $parts->count() - $shown->count();

        return $shown->implode(', ').($extra > 0 ? " +{$extra} more" : '');
    }

    public static function formatQty(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.') ?: '0';
    }
}
