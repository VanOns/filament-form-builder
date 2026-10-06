<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use VanOns\FilamentFormBuilder\Helpers\TemplateHelper;

trait HasFields
{
    public ?bool $large = false;
    public null|int|string $column_span = null;
    public null|int|string $column_start = null;
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
     * The number of grid columns the field spans. A large field is always full
     * width; the stored span only applies when it isn't large, capped so the
     * field never overflows the row it starts in.
     */
    public function getColumnSpan(int $columns): int
    {
        if ($this->large) {
            return $columns;
        }

        $max = $columns - ($this->getColumnStart($columns) ?? 1) + 1;

        if ($this->column_span !== null) {
            return max(1, min((int) $this->column_span, $max));
        }

        return 1;
    }

    /**
     * The grid column the field starts in, capped at the form's column count,
     * or null for auto placement.
     */
    public function getColumnStart(int $columns): ?int
    {
        return $this->column_start !== null ? min((int) $this->column_start, $columns) : null;
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
