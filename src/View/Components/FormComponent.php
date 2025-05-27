<?php

namespace VanOns\FilamentFormBuilder\View\Components;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;

abstract class FormComponent extends Component
{
    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public static function getRules(): array
    {
        return static::rules();
    }

    /**
     * Set fallback values for empty fields & used to inform the user about available placeholders.
     *
     * @return array<int|string, string>
     */
    public static function placeholders(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public static function getPlaceholders(): array
    {
        $mapped = [];

        foreach (static::placeholders() as $key => $value) {
            if (is_int($key)) {
                $mapped[$value] = '-';
            } else {
                $mapped[$key] = $value;
            }
        }

        return $mapped;
    }

    /**
     * Returns an HTML string with the placeholders formatted for display.
     *
     * @return HtmlString
     */
    public static function getPlaceholdersHtmlString(): HtmlString
    {
        $placeholders = array_map(fn ($value) => '{{ $' . $value . ' }}', array_keys(static::getPlaceholders()));

        return new HtmlString('<p>' . __('filament-form-builder::general.you_can_use_placeholders') . '<br/><br/>' . implode('<br/>', $placeholders));
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [];
    }

    /**
     * Find the correct attribute label for a given key.
     *
     * @param string $key
     * @return string
     */
    public static function findAttributeForKey(string $key): string
    {
        $attributes = static::attributes();

        if (array_key_exists($key, $attributes)) {
            return $attributes[$key];
        }

        return Lang::has($transKey = "filament-form-builder::fields.{$key}")
            ? __($transKey)
            : ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [];
    }
}