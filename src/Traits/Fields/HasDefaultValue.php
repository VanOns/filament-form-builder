<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

trait HasDefaultValue
{
    public mixed $defaultValue = null;

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    public static function getDefaultValueComponent(): ?Component
    {
        return TextInput::make('defaultValue')
            ->label(__('filament-form-builder::fields.default_value'));
    }
}
