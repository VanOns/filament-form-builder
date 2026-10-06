<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

trait HasLabel
{
    public ?string $label = null;

    public static function getTypeLabel(): string
    {
        $basename = class_basename(static::class);

        if (Lang::has($key = 'filament-form-builder::fields.types.' . Str::snake($basename))) {
            return __($key);
        }

        return ucfirst(Str::replace('-', ' ', Str::kebab($basename)));
    }

    /**
     * One line on what the field type is for, shown when it is being set up.
     */
    public static function getTypeDescription(): ?string
    {
        $key = 'filament-form-builder::fields.type_descriptions.' . Str::snake(class_basename(static::class));

        return Lang::has($key) ? __($key) : null;
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label ?? static::getTypeLabel();
    }
}
