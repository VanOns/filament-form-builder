<?php

namespace VanOns\FilamentFormBuilder\Forms;

/**
 * Where the fields an editor builds go among the fields of a form type.
 */
final class CustomFields
{
    public static function make(): self
    {
        return new self();
    }
}
