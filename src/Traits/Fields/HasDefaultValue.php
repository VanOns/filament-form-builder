<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

trait HasDefaultValue
{
    public mixed $defaultValue = null;

    public ?string $queryParameter = null;

    public function default(mixed $value): static
    {
        $this->defaultValue = $value;

        return $this;
    }

    /**
     * Fills the field from this parameter of the page's URL, such as the
     * vacancy in ?vacature=Adviseur; without it the default applies.
     */
    public function defaultFromQuery(?string $parameter): static
    {
        $this->queryParameter = $parameter;

        return $this;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    /**
     * What the field starts with on the site. The admin reads only the
     * default, so the URL of a panel page never leaks into a preview.
     */
    public function getInitialValue(): mixed
    {
        $value = filled($this->queryParameter) ? request()->query($this->queryParameter) : null;

        return is_string($value) && $value !== '' ? $value : $this->getDefaultValue();
    }

    public static function getDefaultValueComponent(): ?Component
    {
        return TextInput::make('defaultValue')
            ->label(__('filament-form-builder::fields.default_value'));
    }
}
