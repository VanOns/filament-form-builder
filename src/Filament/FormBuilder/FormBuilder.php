<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;
use VanOns\FilamentFormBuilder\Enums\VisibilityType;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\Helpers\FieldHelper;

class FormBuilder extends Field
{
    protected string $view = 'filament-schemas::components.grid';

    protected function setUp(): void
    {
        $this->schema([
            Repeater::make('fields')
                ->label(__('filament-form-builder::fields.fields'))
                ->default([])
                ->extraItemActions([
                    static::getVisibilityAction(),
                ])
                ->afterStateHydrated(static function (Component $component, ?array $rawState): void {
                    $component->rawState(
                        collect($rawState ?? [])
                            ->mapWithKeys(fn ($itemData) => [(string) Str::uuid() => $itemData])
                            ->toArray(),
                    );
                })
                ->cloneable()
                ->itemLabel(function (?array $state) {
                    $class = $state['fieldType'] ?? null;
                    if ($class && class_exists($class)) {
                        /** @var class-string<FormField> $class */
                        $itemLabel = $class::getItemLabel($state);
                        $label = $class::label();
                    }

                    $join = array_filter([
                        $itemLabel ?? null,
                        $label ?? null,
                    ]);

                    return !empty($join)
                        ? implode(' - ', $join)
                        : '-';
                })
                ->collapsed()
                ->hiddenLabel()
                ->schema([
                    Group::make(fn (Get $get) => [
                        FieldSelect::make('fieldType'),
                        ...FieldHelper::getFields($get('fieldType')),
                    ])->columns(),
                ]),
        ]);
    }

    protected static function getVisibilityGroup(): Group
    {
        return Group::make([
            TextInput::make('visibleWhenKey')
                ->label(__('filament-form-builder::fields.visible_when_key')),
            Select::make('visibleWhenType')
                ->label('Is')
                ->options(VisibilityType::toArray())
                ->default(VisibilityType::EQUALS->value)
                ->required(fn (Get $get) => !empty($get('visibleWhenKey')))
                ->hidden(fn (Get $get) => empty($get('visibleWhenKey')))
                ->reactive(),
            TextInput::make('visibleWhenValue')
                ->visible(fn (Get $get) => !empty($get('visibleWhenKey')) && !empty($get('visibleWhenType') && !in_array($get('visibleWhenType'), ['empty', 'not_empty'])))
                ->label(__('filament-form-builder::fields.value'))
                ->required(fn (Get $get) => !empty($get('visibleWhenKey'))),
        ])->columns(3)->reactive();
    }

    protected static function getVisibilityAction(): Action
    {
        $activeColor = 'success';
        $fieldKey = fn (array $arguments): string => "fields.{$arguments['item']}";

        return Action::make('visibilitySettings')
            ->modalSubmitActionLabel(__('filament-form-builder::general.save'))
            ->label(__('filament-form-builder::general.settings'))
            ->icon('heroicon-o-eye')
            ->color(function (array $arguments, Set $set, Get $get) use ($activeColor, $fieldKey): ?string {
                $key = $fieldKey($arguments);

                return $get("{$key}.visibleWhenKey")
                    ? $activeColor
                    : null;
            })
            ->hidden(function (array $arguments, Repeater $component, Get $get) use ($fieldKey): bool {
                $key = $fieldKey($arguments);
                $fieldType = $get("{$key}.fieldType");

                if (!$fieldType || !class_exists($fieldType) || !method_exists($fieldType, 'hasVisibilitySettings')) {
                    return true;
                }

                return !$fieldType::hasVisibilitySettings();
            })
            ->form(function (): array {
                return [
                    static::getVisibilityGroup(),
                ];
            })
            ->fillForm(function (array $arguments, Set $set, Get $get) use ($fieldKey): array {
                $key = $fieldKey($arguments);

                return [
                    'visibleWhenKey' => $get("{$key}.visibleWhenKey"),
                    'visibleWhenType' => $get("{$key}.visibleWhenType"),
                    'visibleWhenValue' => $get("{$key}.visibleWhenValue"),
                ];
            })
            ->action(function (array $arguments, Set $set, array $data) use ($fieldKey): void {
                $fieldKey = $fieldKey($arguments);

                foreach ($data as $key => $value) {
                    $set("{$fieldKey}.{$key}", $value);
                }
            });
    }
}
