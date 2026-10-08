<?php

namespace VanOns\FilamentFormBuilder\Helpers;

abstract class TypeHelper
{
    /**
     * The classes configured under a key, by the name a form stores, so a class
     * can be renamed or moved without touching the stored forms.
     *
     * @template T of object
     *
     * @param  class-string<T>  $base
     * @return array<string, class-string<T>>
     */
    protected static function configured(string $key, string $base): array
    {
        $types = [];

        foreach ((array) config("filament-form-builder.{$key}", []) as $name => $class) {
            if (is_string($name) && is_string($class) && is_subclass_of($class, $base)) {
                $types[$name] = $class;
            }
        }

        return $types;
    }
}
