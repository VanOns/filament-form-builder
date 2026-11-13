<?php

namespace VanOns\FilamentFormBuilder\Helpers;

use Illuminate\Support\HtmlString;

class AttributeHelper
{
    /**
     * @param array<string, mixed> $attributes
     */
    public static function render(array $attributes): HtmlString
    {
        $rendered = [];

        foreach ($attributes as $key => $value) {
            $rendered[] = $key . '="' . e((string) $value) . '"';
        }

        return new HtmlString(implode(' ', $rendered));
    }
}
