<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use VanOns\FilamentFormBuilder\Helpers\TemplateHelper;

trait HasFields
{
    public null|int|string $column_span = null;
    public ?string $description = null;

    /**
     * The column count of the form this field belongs to, handed over when the
     * form builds its fields. A field on its own falls back to the default.
     */
    protected ?int $gridColumns = null;

    public function setGridColumns(int $columns): static
    {
        $this->gridColumns = $columns;

        return $this;
    }

    public function getGridColumns(): int
    {
        return $this->gridColumns ?? TemplateHelper::defaultColumns();
    }

    /**
     * Capped at the form's column count, so a template that drops columns
     * later never makes a stored field overflow its row.
     */
    public function getColumnSpan(int $columns): int
    {
        return max(1, min((int) ($this->column_span ?? 1), $columns));
    }

    /**
     * @return array<Component>
     */
    abstract public static function getFields(): array;

    /**
     * @return array<Component>
     */
    protected static function getDefaultFields(): array
    {
        return [
            TextInput::make('label')
                ->live(onBlur: true)
                ->label(__('filament-form-builder::fields.label')),
            TextInput::make('description')
                ->label(__('filament-form-builder::fields.description')),
            Checkbox::make('required')
                ->default(false)
                ->columnSpanFull()
                ->label(__('filament-form-builder::fields.required')),
        ];
    }
}
