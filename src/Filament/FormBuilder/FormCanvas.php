<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use VanOns\FilamentFormBuilder\Enums\VisibilityType;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\Helpers\TemplateHelper;

class FormCanvas extends Field
{
    protected string $view = 'filament-form-builder::filament.form-canvas';

    protected int | Closure | null $gridColumns = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);

        $this->gridColumns(static fn (Get $get): int => TemplateHelper::columns($get('template')));

        $this->afterStateHydrated(static function (FormCanvas $component, ?array $rawState): void {
            $component->rawState(
                collect($rawState ?? [])
                    ->mapWithKeys(fn (array $item): array => [(string) Str::uuid() => $item])
                    ->all(),
            );
        });

        $this->mutateDehydratedStateUsing(static fn (?array $state): array => array_values($state ?? []));

        $this->registerActions([
            fn (FormCanvas $component): Action => $component->getAddAction(),
            fn (FormCanvas $component): Action => $component->getEditAction(),
            fn (FormCanvas $component): Action => $component->getCloneAction(),
            fn (FormCanvas $component): Action => $component->getDeleteAction(),
            fn (FormCanvas $component): Action => $component->getReorderAction(),
            fn (FormCanvas $component): Action => $component->getResizeAction('narrow', -1, Heroicon::OutlinedMinusSmall),
            fn (FormCanvas $component): Action => $component->getResizeAction('widen', 1, Heroicon::OutlinedPlusSmall),
        ]);
    }

    public function gridColumns(int | Closure | null $columns): static
    {
        $this->gridColumns = $columns;

        return $this;
    }

    public function getGridColumns(): int
    {
        return max(1, (int) ($this->evaluate($this->gridColumns) ?? TemplateHelper::defaultColumns()));
    }

    /**
     * @return array<int, class-string<FormField>>
     */
    public function getFieldTypes(): array
    {
        return array_values((array) config('filament-form-builder.fields', []));
    }

    /**
     * @return array<string, FormField>
     */
    public function getItems(): array
    {
        $columns = $this->getGridColumns();
        $types = $this->getFieldTypes();
        $items = [];

        foreach ($this->getRawState() ?? [] as $uuid => $data) {
            $type = $data['fieldType'] ?? null;

            if (in_array($type, $types, true)) {
                $items[$uuid] = (new $type($data))->setGridColumns($columns);
            }
        }

        return $items;
    }

    public function getAddAction(): Action
    {
        return Action::make('add')
            ->modalHeading(fn (array $arguments, FormCanvas $component): string => $component->resolveFieldType($arguments['type'] ?? null)::label())
            ->modalSubmitActionLabel(__('filament-form-builder::general.add'))
            ->slideOver()
            ->schema(fn (array $arguments, FormCanvas $component): array => $component->getItemSchema($component->resolveFieldType($arguments['type'] ?? null)))
            ->action(function (array $arguments, array $data, FormCanvas $component): void {
                $position = $arguments['position'] ?? null;

                $component->insertItem($component->withUniqueKey([
                    'fieldType' => $component->resolveFieldType($arguments['type'] ?? null),
                    'column_span' => $component->getGridColumns(),
                    ...$data,
                ]), is_int($position) ? $position : null);
            });
    }

    public function getEditAction(): Action
    {
        return Action::make('edit')
            ->modalHeading(fn (array $arguments, FormCanvas $component): string => $component->resolveFieldType($component->getItemData($arguments)['fieldType'] ?? null)::label())
            ->modalSubmitActionLabel(__('filament-form-builder::general.save'))
            ->slideOver()
            ->fillForm(fn (array $arguments, FormCanvas $component): array => $component->getItemData($arguments))
            ->schema(fn (array $arguments, FormCanvas $component): array => $component->getItemSchema(
                $component->resolveFieldType($component->getItemData($arguments)['fieldType'] ?? null),
                except: $arguments['item'] ?? null,
            ))
            ->action(function (array $arguments, array $data, FormCanvas $component): void {
                $uuid = $arguments['item'];
                $oldKey = ($component->getItems()[$uuid] ?? null)?->getKey();

                $component->updateItem($uuid, fn (array $item): array => $component->withUniqueKey([...$item, ...$data], except: $uuid));

                $newKey = ($component->getItems()[$uuid] ?? null)?->getKey();

                if ($oldKey !== null && $newKey !== null && $oldKey !== $newKey) {
                    $component->renameVisibilityKey($oldKey, $newKey);
                }
            });
    }

    public function getCloneAction(): Action
    {
        return Action::make('clone')
            ->label(__('filament-forms::components.builder.actions.clone.label'))
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->iconButton()
            ->size(Size::Small)
            ->action(function (array $arguments, FormCanvas $component): void {
                $items = $component->getRawState() ?? [];
                $position = array_search($arguments['item'], array_keys($items), true);

                if ($position !== false) {
                    $component->insertItem($component->withUniqueKey($items[$arguments['item']]), $position + 1);
                }
            });
    }

    public function getDeleteAction(): Action
    {
        return Action::make('delete')
            ->label(__('filament-forms::components.builder.actions.delete.label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->iconButton()
            ->size(Size::Small)
            ->action(function (array $arguments, FormCanvas $component): void {
                $items = $component->getRawState() ?? [];
                unset($items[$arguments['item']]);

                $component->rawState($items);
                $component->callAfterStateUpdated();
            });
    }

    public function getReorderAction(): Action
    {
        return Action::make('reorder')
            ->action(function (array $arguments, FormCanvas $component): void {
                $items = $component->getRawState() ?? [];

                $component->rawState(array_intersect_key(
                    [...array_flip($arguments['items'] ?? []), ...$items],
                    $items,
                ));
                $component->callAfterStateUpdated();
            });
    }

    public function getResizeAction(string $name, int $step, Heroicon $icon): Action
    {
        return Action::make($name)
            ->label(__("filament-form-builder::general.canvas.{$name}"))
            ->icon($icon)
            ->color('gray')
            ->iconButton()
            ->size(Size::Small)
            ->disabled(function (array $arguments, FormCanvas $component) use ($step): bool {
                $columns = $component->getGridColumns();
                $span = ($component->getItems()[$arguments['item'] ?? ''] ?? null)?->getColumnSpan($columns) ?? 1;

                return $span + $step < 1 || $span + $step > $columns;
            })
            ->action(function (array $arguments, FormCanvas $component) use ($step): void {
                $columns = $component->getGridColumns();
                $span = $component->getItems()[$arguments['item']]->getColumnSpan($columns);

                $component->updateItem($arguments['item'], fn (array $item): array => [
                    ...$item,
                    'large' => false,
                    'column_start' => null,
                    'column_span' => max(1, min($columns, $span + $step)),
                ]);
            });
    }

    /**
     * @param  class-string<FormField>  $type
     * @return array<Component>
     */
    protected function getItemSchema(string $type, ?string $except = null): array
    {
        $fields = Group::make($type::getFields())->columns(2);

        if (! $type::isInput()) {
            return [$fields];
        }

        $tabs = [
            Tabs\Tab::make(__('filament-form-builder::general.general'))
                ->schema([$fields]),
            Tabs\Tab::make(__('filament-form-builder::fields.advanced'))
                ->schema($this->getAdvancedSchema($type, $except)),
        ];

        if ($type::hasVisibilitySettings()) {
            $tabs[] = Tabs\Tab::make(__('filament-form-builder::fields.conditions'))
                ->schema([$this->getVisibilityGroup($except)]);
        }

        return [
            Tabs::make()
                ->contained(false)
                ->tabs($tabs),
        ];
    }

    /**
     * @param  class-string<FormField>  $type
     * @return array<Component>
     */
    protected function getAdvancedSchema(string $type, ?string $except): array
    {
        $takenKeys = $this->getTakenKeys($except);

        $schema = [
            TextInput::make('key')
                ->label(__('filament-form-builder::fields.key'))
                ->placeholder(fn (Get $get): ?string => filled($get('label')) ? $this->normalizeKey($type, $get('label')) : null)
                ->helperText(__('filament-form-builder::fields.key_helper'))
                ->rules([
                    $type::getKeyValidationRule(),
                    fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($type, $takenKeys): void {
                        $key = $this->normalizeKey($type, (string) $value);

                        if (in_array($key, $type::$reservedKeys, true)) {
                            $fail(__('filament-form-builder::fields.key_reserved'));
                        } elseif (in_array($key, $takenKeys, true)) {
                            $fail(__('filament-form-builder::fields.key_taken'));
                        }
                    },
                ])
                ->validationMessages(['not_regex' => __('filament-form-builder::fields.key_invalid_characters')]),
        ];

        if ($type::canBeHidden()) {
            $schema[] = Toggle::make('hidden')
                ->label(__('filament-form-builder::fields.hidden'))
                ->helperText(__('filament-form-builder::fields.hidden_helper'));
        }

        return array_values(array_filter([
            ...$schema,
            $type::getDefaultValueComponent(),
        ]));
    }

    protected function getVisibilityGroup(?string $except): Group
    {
        $keys = [];

        foreach ($this->getItems() as $uuid => $item) {
            if ($uuid !== $except && $item::isInput()) {
                $keys[$item->getKey()] = $item->getLabel();
            }
        }

        return Group::make([
            Select::make('visibleWhenKey')
                ->label(__('filament-form-builder::fields.visible_when_key'))
                ->options($keys)
                ->live(),
            Select::make('visibleWhenType')
                ->label('Is')
                ->options(VisibilityType::toArray())
                ->default(VisibilityType::EQUALS->value)
                ->formatStateUsing(fn (?string $state): string => $state ?? VisibilityType::EQUALS->value)
                ->required(fn (Get $get): bool => filled($get('visibleWhenKey')))
                ->visible(fn (Get $get): bool => filled($get('visibleWhenKey')))
                ->live(),
            TextInput::make('visibleWhenValue')
                ->label(__('filament-form-builder::fields.value'))
                ->required(fn (Get $get): bool => filled($get('visibleWhenKey')))
                ->visible(fn (Get $get): bool => filled($get('visibleWhenKey'))
                    && ! in_array($get('visibleWhenType'), [VisibilityType::EMPTY->value, VisibilityType::NOT_EMPTY->value], true)),
        ]);
    }

    /**
     * @return class-string<FormField>
     */
    protected function resolveFieldType(mixed $type): string
    {
        if (! in_array($type, $this->getFieldTypes(), true)) {
            throw new InvalidArgumentException('Unknown field type.');
        }

        return $type;
    }

    /**
     * Fixes the key the moment a field is stored, so renaming its label later
     * does not break the conditions, placeholders and answers that use it.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function withUniqueKey(array $item, ?string $except = null): array
    {
        $type = $this->resolveFieldType($item['fieldType'] ?? null);

        if (! $type::isInput()) {
            return $item;
        }

        $field = new $type([...$item, 'key' => filled($item['key'] ?? null) ? $this->normalizeKey($type, (string) $item['key']) : null]);
        $base = $field->getKey();
        $takenKeys = [...$this->getTakenKeys($except), ...$type::$reservedKeys];

        $key = $base;

        for ($suffix = 2; in_array($key, $takenKeys, true); $suffix++) {
            $key = "{$base}_{$suffix}";
        }

        return [...$item, 'key' => $key];
    }

    /**
     * @param  class-string<FormField>  $type
     */
    protected function normalizeKey(string $type, string $key): string
    {
        return $type::cleanKey(Str::snake($key));
    }

    /**
     * @return array<int, string>
     */
    protected function getTakenKeys(?string $except): array
    {
        $keys = [];

        foreach ($this->getItems() as $uuid => $item) {
            if ($uuid !== $except && $item::isInput()) {
                $keys[] = $item->getKey();
            }
        }

        return $keys;
    }

    protected function renameVisibilityKey(string $from, string $to): void
    {
        $items = $this->getRawState() ?? [];

        foreach ($items as $uuid => $item) {
            if (($item['visibleWhenKey'] ?? null) === $from) {
                $items[$uuid]['visibleWhenKey'] = $to;
            }
        }

        $this->rawState($items);
        $this->callAfterStateUpdated();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function getItemData(array $arguments): array
    {
        return $this->getRawState()[$arguments['item'] ?? ''] ?? [];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function insertItem(array $item, ?int $position): void
    {
        $items = $this->getRawState() ?? [];
        $position ??= count($items);

        $this->rawState([
            ...array_slice($items, 0, $position, preserve_keys: true),
            (string) Str::uuid() => $item,
            ...array_slice($items, $position, preserve_keys: true),
        ]);
        $this->callAfterStateUpdated();
    }

    /**
     * @param  Closure(array<string, mixed>): array<string, mixed>  $update
     */
    protected function updateItem(string $uuid, Closure $update): void
    {
        $items = $this->getRawState() ?? [];

        if (! isset($items[$uuid])) {
            return;
        }

        $items[$uuid] = $update($items[$uuid]);

        $this->rawState($items);
        $this->callAfterStateUpdated();
    }
}
