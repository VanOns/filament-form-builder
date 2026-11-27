<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * Checkboxes, of which a visitor may have to tick at least or at most so many.
 * The server checks that; a browser has no attribute for it.
 */
class CheckboxListField extends ChoiceField
{
    public int|string|null $minItems = null;

    public int|string|null $maxItems = null;

    public function minItems(?int $items): static
    {
        $this->minItems = $items;

        return $this;
    }

    public function maxItems(?int $items): static
    {
        $this->maxItems = $items;

        return $this;
    }

    public function getMinItems(): ?int
    {
        return is_numeric($this->minItems) && (int) $this->minItems > 0 ? (int) $this->minItems : null;
    }

    public function getMaxItems(): ?int
    {
        return is_numeric($this->maxItems) && (int) $this->maxItems > 0 ? (int) $this->maxItems : null;
    }

    public static function allowsMultiple(): bool
    {
        return true;
    }

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedQueueList;
    }

    protected function getCountRules(): array
    {
        $min = $this->getMinItems();
        $max = $this->getMaxItems();

        return [
            ...($min === null ? [] : ["min:{$min}"]),
            ...($max === null ? [] : ["max:{$max}"]),
        ];
    }

    public static function getValidationFields(): array
    {
        return [
            TextInput::make('minItems')
                ->label(__('filament-form-builder::fields.min_items'))
                ->integer()
                ->minValue(1),

            TextInput::make('maxItems')
                ->label(__('filament-form-builder::fields.max_items'))
                ->integer()
                ->minValue(fn (Get $get): int => is_numeric($get('minItems')) ? max(1, (int) $get('minItems')) : 1),
        ];
    }
}
