<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

trait HasLabel
{
    public ?string $label = null;

    public ?string $columnLabel = null;

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

    /**
     * A short name for the tables, the export, the filters and the mails,
     * where a whole question would be too long.
     */
    public function columnLabel(?string $label): static
    {
        $this->columnLabel = $label;

        return $this;
    }

    public function getColumnLabel(): string
    {
        return filled($this->columnLabel) ? $this->columnLabel : $this->getLabel();
    }

    public static function hasColumnLabelSetting(): bool
    {
        return true;
    }
}
