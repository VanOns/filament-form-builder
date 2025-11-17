<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

trait HasItemLabel
{
    public static string $itemLabelField = 'label';

    public static function getItemLabelField(): string
    {
        return static::$itemLabelField;
    }

    public static function getItemLabel(array $item): ?string
    {
        $itemLabel = $item[static::getItemLabelField()] ?? null;

        return $itemLabel
            ? \Str::limit($itemLabel, 50)
            : null;
    }
}