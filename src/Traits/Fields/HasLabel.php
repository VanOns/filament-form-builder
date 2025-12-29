<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Illuminate\Support\Str;

trait HasLabel
{
    public ?string $label;

    public static function label(): string
    {
        $field = Str::replace('-', ' ', Str::kebab(class_basename(static::class)));
        return ucfirst($field);
    }

    public function getLabel(): string
    {
        return $this->label ?? static::label();
    }
}
