<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

trait HasLabel
{
    public ?string $label = null;

    public static function label(): string
    {
        $basename = class_basename(static::class);

        if (Lang::has($key = 'filament-form-builder::fields.types.' . Str::snake($basename))) {
            return __($key);
        }

        return ucfirst(Str::replace('-', ' ', Str::kebab($basename)));
    }

    public function getLabel(): string
    {
        return $this->label ?? static::label();
    }
}
