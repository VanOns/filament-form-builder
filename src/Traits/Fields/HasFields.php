<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use VanOns\FilamentFormBuilder\Helpers\TemplateHelper;

trait HasFields
{
    public ?bool $large = false;
    public null|int|string $column_span = null;
    public null|int|string $column_start = null;
    public ?string $description;

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
                ->reactive()
                ->helperText(static::getKeyHelperText(...))
                ->label(__('filament-form-builder::fields.label')),
            TextInput::make('key')
                ->required()
                ->reactive()
                ->visible(fn (Get $get) => $get('set_key'))
                ->rules([static::getKeyValidationRule()])
                ->validationMessages(['not_regex' => __('filament-form-builder::fields.key_invalid_characters')])
                ->label(__('filament-form-builder::fields.key')),
            Group::make([
                Checkbox::make('required')
                    ->default(false)
                    ->label(__('filament-form-builder::fields.required')),
                Checkbox::make('large')
                    ->default(false)
                    ->live()
                    ->label(__('filament-form-builder::fields.large')),
                Checkbox::make('set_key')
                    ->default(false)
                    ->label(__('filament-form-builder::fields.set_key'))
                    ->afterStateUpdated(fn (Set $set) => $set('key', ''))
                    ->reactive(),
            ])->columnStart(1)
                ->columnSpanFull()
                ->columns(4),
            Group::make([
                Select::make('column_span')
                    ->default(1)
                    ->formatStateUsing(fn (mixed $state): int => (int) ($state ?? 1))
                    ->options(static::getColumnOptions(...))
                    ->selectablePlaceholder(false)
                    ->label(__('filament-form-builder::fields.column_span')),
                Select::make('column_start')
                    ->options(static::getColumnOptions(...))
                    ->placeholder(__('filament-form-builder::fields.column_start_auto'))
                    ->label(__('filament-form-builder::fields.column_start')),
            ])->visible(fn (Get $get): bool => static::hasColumnSettings($get) && ! $get('large'))
                ->columnStart(1)
                ->columnSpanFull()
                ->columns(4),
            TextInput::make('description')
                ->columnStart(1)
                ->label(__('filament-form-builder::fields.description')),
        ];
    }

    /**
     * Whether the column span/start selects are available. Always the case for
     * forms with more than 2 columns; opt in for the rest via the
     * `field_column_settings` config flag.
     */
    protected static function hasColumnSettings(Get $get): bool
    {
        return config('filament-form-builder.field_column_settings', false)
            || static::getFormColumns($get) > 2;
    }

    /**
     * @return array<int, int>
     */
    protected static function getColumnOptions(Get $get): array
    {
        $range = range(1, static::getFormColumns($get));

        return array_combine($range, $range);
    }

    protected static function getFormColumns(Get $get): int
    {
        return TemplateHelper::columns($get('../../../template'));
    }
}
