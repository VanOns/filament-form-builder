<?php

namespace VanOns\FilamentFormBuilder\Helpers;

class AttributeHelper
{
    /**
     * @param array<string, mixed> $attributes
     * @return string
     */
    public static function arrayToString(array $attributes): string
    {
        $extraAttributes = '';

        foreach ($attributes as $key => $value) {
            $extraAttributes .= "{$key}={$value} ";
        }

        return trim($extraAttributes);
    }
}
