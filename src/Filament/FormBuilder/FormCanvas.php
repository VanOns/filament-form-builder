<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use VanOns\FilamentFormBuilder\Enums\FieldWidth;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\FilamentFormBuilderPlugin;
use VanOns\FilamentFormBuilder\Forms\CustomFields;
use VanOns\FilamentFormBuilder\Helpers\FieldTypeHelper;

class FormCanvas extends Field
{
    protected string $view = 'filament-form-builder::filament.form-canvas';

    /**
     * @var array<int, FormField|CustomFields>|Closure
     */
    protected array | Closure $fixedFields = [];

    /**
     * @var array<int, string>|Closure
     */
    protected array | Closure $reservedKeys = [];

    protected ?Closure $afterKeyRenamed = null;

    protected ?Closure $keyUsages = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);

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
            fn (FormCanvas $component): Action => $component->getResizeAction(),
        ]);
    }

    /**
     * The fields a form type has in code, shown around the editor's fields where
     * CustomFields::make() sits. The canvas cannot change them.
     *
     * @param  array<int, FormField|CustomFields>|Closure  $fields
     */
    public function fixedFields(array | Closure $fields): static
    {
        $this->fixedFields = $fields;

        return $this;
    }

    /**
     * Keys the form type fills in itself, such as the values it adds before
     * storing, which no field of the editor's may take.
     *
     * @param  array<int, string>|Closure  $keys
     */
    public function reservedKeys(array | Closure $keys): static
    {
        $this->reservedKeys = $keys;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getReservedKeys(): array
    {
        return (array) $this->evaluate($this->reservedKeys);
    }

    /**
     * Whether the type took this editor's field's key in code, which leaves the
     * field out of the form until it is deleted or gets another key.
     */
    public function isKeyTakenByType(FormField $field): bool
    {
        $typeKeys = [
            ...array_map(fn (FormField $fixed): string => $fixed->getKey(), $this->getFixedInputs()),
            ...$this->getReservedKeys(),
        ];

        return $field::isInput() && in_array($field->getKey(), $typeKeys, true);
    }

    /**
     * Runs after an editor renamed a key, so whatever outside the canvas still
     * names the old key can follow. Receives `$from` and `$to`.
     */
    public function afterKeyRenamed(?Closure $callback): static
    {
        $this->afterKeyRenamed = $callback;

        return $this;
    }

    /**
     * Lists what outside the canvas still names one of a field's keys, for the
     * warning before it is deleted. Receives `$keys`, returns descriptions.
     */
    public function keyUsagesUsing(?Closure $callback): static
    {
        $this->keyUsages = $callback;

        return $this;
    }

    /**
     * Where a field is still named: the conditions of other fields, and
     * whatever outside the canvas reports.
     *
     * @return list<string>
     */
    public function getKeyUsages(string $uuid): array
    {
        $field = $this->getItems()[$uuid] ?? null;

        if ($field === null) {
            return [];
        }

        $keys = array_values(array_unique([$field->getKey(), ...array_keys($field->getSubmissionColumns())]));
        $usages = [];

        foreach ($this->getItems() as $otherUuid => $other) {
            $rules = $otherUuid === $uuid ? [] : $other->getConditions()->rules;

            if (array_intersect(array_column($rules, 'key'), $keys) !== []) {
                $usages[] = __('filament-form-builder::general.canvas.usage_conditions', ['label' => $other->getLabel()]);
            }
        }

        return [...$usages, ...$this->evaluate($this->keyUsages, ['keys' => $keys]) ?? []];
    }

    /**
     * Whether an editor may add fields: on a canvas of its own, or where the
     * form type placed CustomFields::make().
     */
    public function acceptsFields(): bool
    {
        $fields = $this->evaluate($this->fixedFields) ?? [];

        foreach ($fields as $field) {
            if ($field instanceof CustomFields) {
                return true;
            }
        }

        return $fields === [];
    }

    /**
     * @return array{0: array<int, FormField>, 1: array<int, FormField>}
     */
    public function getFixedFields(): array
    {
        $before = [];
        $after = [];
        $isAfter = false;

        foreach ($this->evaluate($this->fixedFields) ?? [] as $field) {
            if ($field instanceof CustomFields) {
                $isAfter = true;
            } elseif ($isAfter) {
                $after[] = $field;
            } else {
                $before[] = $field;
            }
        }

        return [$before, $after];
    }

    /**
     * @return array<int, FormField>
     */
    protected function getFixedInputs(): array
    {
        return array_values(array_filter(array_merge(...$this->getFixedFields()), fn (FormField $field): bool => $field::isInput()));
    }

    /**
     * @return array<string, class-string<FormField>>
     */
    public function getFieldTypes(): array
    {
        return FieldTypeHelper::all();
    }

    /**
     * @return array<string, FormField>
     */
    public function getItems(): array
    {
        $items = [];

        foreach ($this->getRawState() ?? [] as $uuid => $data) {
            if ($type = FieldTypeHelper::resolve($data['type'] ?? null)) {
                $items[$uuid] = new $type($data);
            }
        }

        return $items;
    }

    public function getAddAction(): Action
    {
        return Action::make('add')
            ->modalHeading(fn (array $arguments, FormCanvas $component): string => $component->resolveFieldType($arguments['type'] ?? null)::getTypeLabel())
            ->modalDescription(fn (array $arguments, FormCanvas $component): ?string => $component->resolveFieldType($arguments['type'] ?? null)::getTypeDescription())
            ->modalIcon(fn (array $arguments, FormCanvas $component): string | BackedEnum => $component->resolveFieldType($arguments['type'] ?? null)::icon())
            ->modalSubmitActionLabel(__('filament-form-builder::general.add'))
            ->slideOver()
            ->schema(fn (array $arguments, FormCanvas $component): array => $component->getItemSchema($component->resolveFieldType($arguments['type'] ?? null)))
            ->action(function (array $arguments, array $data, FormCanvas $component): void {
                $position = $arguments['position'] ?? null;

                $component->resizeItems(is_array($arguments['spans'] ?? null) ? $arguments['spans'] : []);
                $component->insertItem($component->withUniqueKey([
                    'type' => $arguments['type'],
                    'column_span' => $component->getNewFieldWidth(
                        is_int($position) ? $position : null,
                        $component->resolveFieldType($arguments['type'])::minWidth(),
                        FieldWidth::tryFrom((int) ($arguments['width'] ?? 0)),
                    )->value,
                    ...$data,
                ]), is_int($position) ? $position : null);
            });
    }

    public function getEditAction(): Action
    {
        return Action::make('edit')
            ->modalHeading(fn (array $arguments, FormCanvas $component): string => $component->resolveFieldType($component->getItemData($arguments)['type'] ?? null)::getTypeLabel())
            ->modalDescription(fn (array $arguments, FormCanvas $component): ?string => $component->resolveFieldType($component->getItemData($arguments)['type'] ?? null)::getTypeDescription())
            ->modalIcon(fn (array $arguments, FormCanvas $component): string | BackedEnum => $component->resolveFieldType($component->getItemData($arguments)['type'] ?? null)::icon())
            ->modalSubmitActionLabel(__('filament-form-builder::general.save'))
            ->slideOver()
            ->fillForm(fn (array $arguments, FormCanvas $component): array => $component->getItemData($arguments))
            ->schema(fn (array $arguments, FormCanvas $component): array => $component->getItemSchema(
                $component->resolveFieldType($component->getItemData($arguments)['type'] ?? null),
                except: $arguments['item'] ?? null,
            ))
            ->action(function (array $arguments, array $data, FormCanvas $component): void {
                $uuid = $arguments['item'];
                $oldKey = ($component->getItems()[$uuid] ?? null)?->getKey();

                $component->updateItem($uuid, fn (array $item): array => $component->withUniqueKey([...$item, ...$data], except: $uuid));

                $newKey = ($component->getItems()[$uuid] ?? null)?->getKey();

                if ($oldKey !== null && $newKey !== null && $oldKey !== $newKey) {
                    $component->renameConditionKey($oldKey, $newKey);
                    $component->evaluate($component->afterKeyRenamed, ['from' => $oldKey, 'to' => $newKey]);
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
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedDocumentDuplicate)
            ->modalHeading(fn (array $arguments, FormCanvas $component): string => __('filament-form-builder::general.canvas.clone_heading', [
                'label' => ($component->getItems()[$arguments['item'] ?? ''] ?? null)?->getLabel() ?? '',
            ]))
            ->modalDescription(__('filament-form-builder::general.canvas.clone_description'))
            ->modalSubmitActionLabel(__('filament-forms::components.builder.actions.clone.label'))
            ->action(function (array $arguments, FormCanvas $component): void {
                $uuid = (string) ($arguments['item'] ?? '');
                $item = ($component->getRawState() ?? [])[$uuid] ?? null;

                if ($item === null) {
                    return;
                }

                ['position' => $position, 'width' => $width, 'spans' => $spans] = $component->getCopySlot($uuid);

                $component->resizeItems($spans);
                $component->insertItem($component->withUniqueKey([...$item, 'column_span' => $width->value]), $position);
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
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments, FormCanvas $component): string => __('filament-form-builder::general.canvas.delete_heading', [
                'label' => ($component->getItems()[$arguments['item'] ?? ''] ?? null)?->getLabel() ?? '',
            ]))
            ->modalDescription(function (array $arguments, FormCanvas $component): string {
                $usages = $component->getKeyUsages((string) ($arguments['item'] ?? ''));

                return trim(__('filament-form-builder::general.canvas.delete_description') . ' ' . ($usages === [] ? '' : __('filament-form-builder::general.canvas.used_in', [
                    'places' => Arr::join($usages, ', ', __('filament-form-builder::general.notifications.list_and')),
                ])));
            })
            ->modalSubmitActionLabel(__('filament-forms::components.builder.actions.delete.label'))
            ->action(function (array $arguments, FormCanvas $component): void {
                $uuid = (string) ($arguments['item'] ?? '');
                $spans = $component->fillRowWithout($uuid);
                $items = $component->getRawState() ?? [];
                unset($items[$uuid]);

                $component->rawState($items);
                $component->resizeItems($spans);
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
                $component->resizeItems(is_array($arguments['spans'] ?? null) ? $arguments['spans'] : []);
            });
    }

    public function getResizeAction(): Action
    {
        return Action::make('resize')
            ->action(function (array $arguments, FormCanvas $component): void {
                $uuid = (string) ($arguments['item'] ?? '');
                $width = FieldWidth::tryFrom((int) ($arguments['width'] ?? 0));
                $field = $component->getItems()[$uuid] ?? null;

                if ($width === null || ! $width->isAvailable() || $field === null) {
                    return;
                }

                $width = FieldWidth::fit($width->value, $field::minWidth());

                $straightened = $component->straightenRow($uuid, $width);

                $component->resizeItems([$uuid => $width->value, ...$straightened['spans']]);
                $component->moveAfterRow($straightened['pushed'], $straightened['row']);
            });
    }

    /**
     * @param  array<mixed>  $spans  uuid => span; a span that is no width counts for nothing
     */
    protected function resizeItems(array $spans): void
    {
        $fields = $this->getItems();
        $items = $this->getRawState() ?? [];

        foreach ($spans as $uuid => $span) {
            $width = FieldWidth::tryFrom((int) $span);

            if ($width !== null && $width->isAvailable() && isset($fields[$uuid])) {
                $items[$uuid]['column_span'] = FieldWidth::fit($width->value, $fields[$uuid]::minWidth())->value;
            }
        }

        $this->rawState($items);
        $this->callAfterStateUpdated();
    }

    /**
     * The widths the picker offers a field: those the layout has, none below
     * what its type works at.
     *
     * @return list<FieldWidth>
     */
    public function getWidthOptions(FormField $field): array
    {
        return array_values(array_filter(FieldWidth::available(), fn (FieldWidth $width): bool => $width->value >= $field::minWidth()->value));
    }

    public function hasFixedFields(): bool
    {
        return array_merge(...$this->getFixedFields()) !== [];
    }

    /**
     * The field types grouped the way the palette lists them.
     *
     * @return array<string, array<string, class-string<FormField>>>
     */
    public function getPaletteGroups(): array
    {
        $groups = ['input' => [], 'choice' => [], 'layout' => []];

        $without = FilamentFormBuilderPlugin::get()->getWithoutFields();

        foreach ($this->getFieldTypes() as $name => $class) {
            if ($class::isAvailable() && !in_array($name, $without, true)) {
                $groups[$class::paletteGroup()][$name] = $class;
            }
        }

        return array_filter($groups);
    }

    /**
     * When a field shows, for its badge on the canvas: its one rule, or how
     * many it has and how they combine.
     */
    public function getConditionBadge(FormField $field): ?string
    {
        if (! $field->hasConditions()) {
            return null;
        }

        ['match' => $match, 'rules' => $rules] = $field->getConditions()->toArray();

        return (new ConditionsEditor($this->getConditionFields(null)))->badge($rules, $match);
    }

    /**
     * The fields another field's conditions can look at, by key.
     *
     * @return array<string, FormField>
     */
    protected function getConditionFields(?string $except): array
    {
        $fields = [];

        foreach ($this->getFixedInputs() as $field) {
            $fields[$field->getKey()] = $field;
        }

        foreach ($this->getItems() as $uuid => $item) {
            if ($uuid !== $except && $item::isInput()) {
                $fields[$item->getKey()] = $item;
            }
        }

        return $fields;
    }

    /**
     * A hidden field takes no room on the page, so it gets a row of its own here.
     */
    public function getCanvasWidth(FormField $field): FieldWidth
    {
        return $field->isHidden() ? FieldWidth::FULL : $field->getWidth();
    }

    /**
     * A new field takes the room left on the row it lands on, so building a row
     * needs no resizing. Where that room is too narrow it gets a row of its own.
     * A width the canvas already showed while the field was dragged wins.
     */
    public function getNewFieldWidth(?int $position, FieldWidth $minimum, ?FieldWidth $shown = null): FieldWidth
    {
        if ($shown !== null && $shown->isAvailable()) {
            return FieldWidth::fit($shown->value, $minimum);
        }

        [$before] = $this->getFixedFields();
        $flow = $this->getFlow();
        $position = count($before) + ($position ?? count($this->getItems()));

        foreach ([$position - 1, $position] as $neighbour) {
            foreach ($this->toRows($flow) as $row) {
                $fill = FieldWidth::within(FieldWidth::FULL->value - array_sum(array_map(fn (int $index): int => $flow[$index]['span'], $row)), $minimum);

                if (in_array($neighbour, $row, true) && $fill !== null) {
                    return $fill;
                }
            }
        }

        return FieldWidth::FULL;
    }

    /**
     * How the other fields on a resized field's row follow so the row stays
     * full: they take the room left at widths closest to their own. Fields at
     * the end that no longer fit move to a row of their own right below, which
     * they fill. A row that was not full and still fits is left alone.
     *
     * @return array{spans: array<string, int>, pushed: list<string>, row: list<string>}
     */
    public function straightenRow(string $uuid, FieldWidth $width): array
    {
        $flow = $this->getFlow();

        foreach ($this->toRows($flow) as $row) {
            $entries = array_map(fn (int $index): array => $flow[$index], $row);
            $self = array_search($uuid, array_column($entries, 'uuid'), true);

            if ($self === false) {
                continue;
            }

            $unchanged = ['spans' => [], 'pushed' => [], 'row' => array_values(array_filter(array_column($entries, 'uuid')))];
            $used = array_sum(array_column($entries, 'span'));

            if ($used < FieldWidth::FULL->value && $used - $entries[$self]['span'] + $width->value <= FieldWidth::FULL->value) {
                return $unchanged;
            }

            $room = FieldWidth::FULL->value - $width->value;
            $movable = [];

            foreach ($entries as $index => $entry) {
                if ($index === $self) {
                    continue;
                }

                if ($entry['uuid'] === null) {
                    $room -= $entry['span'];
                } else {
                    $movable[$entry['uuid']] = $entry;
                }
            }

            for ($keep = count($movable); $keep >= 0; $keep--) {
                $kept = array_slice($movable, 0, $keep, true);
                $pushed = array_slice($movable, $keep, null, true);
                $spans = $this->closestSpans(array_column($kept, 'span'), array_column($kept, 'min'), $room);
                $below = $pushed === [] ? [] : $this->closestSpans(array_column($pushed, 'span'), array_column($pushed, 'min'), FieldWidth::FULL->value);

                if ($spans !== null && $below !== null) {
                    return [
                        'spans' => [...array_combine(array_keys($kept), $spans), ...array_combine(array_keys($pushed), $below)],
                        'pushed' => array_keys($pushed),
                        'row' => $unchanged['row'],
                    ];
                }
            }

            return $unchanged;
        }

        return ['spans' => [], 'pushed' => [], 'row' => []];
    }

    /**
     * Moves the given fields to right after the rest of their row, so they
     * start a row of their own instead of pushing into the next one.
     *
     * @param  list<string>  $pushed
     * @param  list<string>  $row
     */
    protected function moveAfterRow(array $pushed, array $row): void
    {
        if ($pushed === []) {
            return;
        }

        $items = $this->getRawState() ?? [];
        $order = array_values(array_diff(array_keys($items), $pushed));
        $after = 0;

        foreach (array_diff($row, $pushed) as $uuid) {
            $after = max($after, (int) array_search($uuid, $order, true) + 1);
        }

        array_splice($order, $after, 0, $pushed);

        $this->rawState(array_replace(array_flip($order), $items));
        $this->callAfterStateUpdated();
    }

    /**
     * Spans for the other fields on a field's row that fill the room it leaves,
     * so the row stays full once the field is gone. Empty when a field from
     * code shares the row, as that one cannot change width.
     *
     * @return array<string, int>
     */
    public function fillRowWithout(string $uuid): array
    {
        $flow = $this->getFlow();

        foreach ($this->toRows($flow) as $row) {
            $entries = array_map(fn (int $index): array => $flow[$index], $row);

            if (! in_array($uuid, array_column($entries, 'uuid'), true)) {
                continue;
            }

            $others = [];

            foreach ($entries as $entry) {
                if ($entry['uuid'] === null) {
                    return [];
                }

                if ($entry['uuid'] !== $uuid) {
                    $others[$entry['uuid']] = $entry;
                }
            }

            $spans = $this->closestSpans(array_column($others, 'span'), array_column($others, 'min'), FieldWidth::FULL->value);

            return $spans === null ? [] : array_combine(array_keys($others), $spans);
        }

        return [];
    }

    /**
     * Where a copy goes: next to its original when the row has room or can be
     * shared equally, otherwise on a row of its own below it.
     *
     * @return array{position: int, width: FieldWidth, spans: array<string, int>}
     */
    public function getCopySlot(string $uuid): array
    {
        [$before] = $this->getFixedFields();
        $flow = $this->getFlow();
        $count = count($this->getItems());
        $position = (int) array_search($uuid, array_keys($this->getItems()), true) + 1;

        foreach ($this->toRows($flow) as $row) {
            $entries = array_map(fn (int $index): array => $flow[$index], $row);

            if (! in_array($uuid, array_column($entries, 'uuid'), true)) {
                continue;
            }

            $minimum = $flow[count($before) + $position - 1]['min'];
            $fill = FieldWidth::within(FieldWidth::FULL->value - array_sum(array_column($entries, 'span')), FieldWidth::from($minimum));

            if ($fill !== null) {
                return ['position' => $position, 'width' => $fill, 'spans' => []];
            }

            $shared = FieldWidth::FULL->value % (count($entries) + 1) === 0
                ? FieldWidth::tryFrom(intdiv(FieldWidth::FULL->value, count($entries) + 1))
                : null;
            $shared = $shared?->isAvailable() ? $shared : null;
            $spans = [];

            foreach ($entries as $entry) {
                if ($shared === null || $entry['uuid'] === null || $entry['min'] > $shared->value || $minimum > $shared->value) {
                    $shared = null;
                    break;
                }

                $spans[$entry['uuid']] = $shared->value;
            }

            if ($shared !== null) {
                return ['position' => $position, 'width' => $shared, 'spans' => $spans];
            }

            $afterRow = max($row) - count($before) + 1;

            return ['position' => min(max($position, $afterRow), $count), 'width' => FieldWidth::FULL, 'spans' => []];
        }

        return ['position' => $position, 'width' => FieldWidth::FULL, 'spans' => []];
    }

    /**
     * Everything on the canvas in order. Only the editor's own fields carry a
     * uuid, as only they can be resized.
     *
     * @return list<array{uuid: ?string, span: int, min: int, group: string}>
     */
    protected function getFlow(): array
    {
        [$before, $after] = $this->getFixedFields();
        $flow = [];

        foreach ($before as $field) {
            $flow[] = ['uuid' => null, 'span' => $this->getCanvasWidth($field)->value, 'min' => $field::minWidth()->value, 'group' => 'before'];
        }

        foreach ($this->getItems() as $uuid => $item) {
            $flow[] = ['uuid' => $uuid, 'span' => $this->getCanvasWidth($item)->value, 'min' => $item::minWidth()->value, 'group' => 'canvas'];
        }

        foreach ($after as $field) {
            $flow[] = ['uuid' => null, 'span' => $this->getCanvasWidth($field)->value, 'min' => $field::minWidth()->value, 'group' => 'after'];
        }

        return $flow;
    }

    /**
     * Fields fill a row in order and move to the next when they do not fit.
     * The editor's fields form a block of their own, so no row holds both
     * those and fields from code.
     *
     * @param  list<array{uuid: ?string, span: int, min: int, group: string}>  $flow
     * @return list<list<int>> the positions in the flow on each row
     */
    protected function toRows(array $flow): array
    {
        $rows = [];
        $column = FieldWidth::FULL->value;
        $group = null;

        foreach ($flow as $index => $entry) {
            if ($column + $entry['span'] > FieldWidth::FULL->value || $entry['group'] !== $group) {
                $rows[] = [];
                $column = 0;
            }

            $rows[count($rows) - 1][] = $index;
            $column += $entry['span'];
            $group = $entry['group'];
        }

        return $rows;
    }

    /**
     * The widths closest to the given spans that fill exactly the room, none
     * below its minimum.
     *
     * @param  list<int>  $spans
     * @param  list<int>  $minimums
     * @return list<int>|null
     */
    protected function closestSpans(array $spans, array $minimums, int $room): ?array
    {
        $closest = null;
        $score = [PHP_INT_MAX, PHP_INT_MAX];

        foreach ($this->spanCombinations($minimums, $room) as $combination) {
            $changes = array_map(fn (int $new, int $old): int => $new - $old, $combination, $spans);

            // The smallest change overall, and of those the most evenly spread.
            $candidate = [
                array_sum(array_map(abs(...), $changes)),
                array_sum(array_map(fn (int $change): int => $change ** 2, $changes)),
            ];

            if ($candidate < $score) {
                $closest = $combination;
                $score = $candidate;
            }
        }

        return $closest;
    }

    /**
     * @param  list<int>  $minimums
     * @return list<list<int>>
     */
    protected function spanCombinations(array $minimums, int $room): array
    {
        if ($minimums === [] || $room <= 0) {
            return $minimums === [] && $room === 0 ? [[]] : [];
        }

        $minimum = array_shift($minimums);
        $combinations = [];

        foreach (FieldWidth::available() as $width) {
            if ($width->value < $minimum) {
                continue;
            }

            foreach ($this->spanCombinations($minimums, $room - $width->value) as $rest) {
                $combinations[] = [$width->value, ...$rest];
            }
        }

        return $combinations;
    }

    /**
     * @param  class-string<FormField>  $type
     * @return array<Component>
     */
    protected function getItemSchema(string $type, ?string $except = null): array
    {
        $fields = [
            Group::make($type::getFields())->columns(2),
            View::make('filament-form-builder::filament.partials.settings-preview')
                ->viewData(fn (Get $get): array => ['field' => new $type((array) $get(''))]),
        ];

        if (! $type::isInput()) {
            return $fields;
        }

        $tabs = [
            Tabs\Tab::make(__('filament-form-builder::general.general'))
                ->schema($fields),
            Tabs\Tab::make(__('filament-form-builder::fields.advanced'))
                ->schema($this->getAdvancedSchema($type, $except)),
        ];

        if ($type::hasConditionSettings()) {
            $tabs[] = Tabs\Tab::make(__('filament-form-builder::fields.conditions'))
                ->schema($this->getConditionsSchema($except));
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

                        if (in_array($key, $type::reservedKeys(), true)) {
                            $fail(__('filament-form-builder::fields.key_reserved'));
                        } elseif (in_array($key, $takenKeys, true)) {
                            $fail(__('filament-form-builder::fields.key_taken'));
                        }
                    },
                ])
                ->validationMessages(['not_regex' => __('filament-form-builder::fields.key_invalid_characters')]),
        ];

        $overviews = array_filter([
            $type::hasColumnLabelSetting() ? TextInput::make('columnLabel')
                ->label(__('filament-form-builder::fields.column_label'))
                ->placeholder(fn (Get $get): ?string => $get('label'))
                ->helperText(__('filament-form-builder::fields.column_label_helper')) : null,
            Toggle::make('showColumn')
                ->label(__('filament-form-builder::fields.show_column'))
                ->helperText(__('filament-form-builder::fields.show_column_helper')),
        ]);

        $default = $type::getDefaultValueComponent();

        $site = array_filter([
            $type::canBeHidden() ? Toggle::make('hidden')
                ->label(__('filament-form-builder::fields.hidden'))
                ->helperText(__('filament-form-builder::fields.hidden_helper')) : null,
            $default,
            $default === null ? null : TextInput::make('queryParameter')
                ->label(__('filament-form-builder::fields.query_parameter'))
                ->prefix('?')
                ->alphaDash()
                ->helperText(__('filament-form-builder::fields.query_parameter_helper')),
        ]);

        $group = fn (string $heading, array $components): Section => Section::make(__("filament-form-builder::fields.{$heading}"))
            ->contained(false)
            ->extraAttributes(['class' => 'ffb-settings-group'])
            ->schema(array_values($components));

        return array_filter([
            ...$schema,
            $group('in_overviews', $overviews),
            $site === [] ? null : $group('on_the_site', $site),
        ]);
    }

    /**
     * @return array<Component>
     */
    protected function getConditionsSchema(?string $except): array
    {
        return (new ConditionsEditor($this->getConditionFields($except)))->schema();
    }

    /**
     * @return class-string<FormField>
     */
    protected function resolveFieldType(mixed $type): string
    {
        return FieldTypeHelper::resolve($type) ?? throw new InvalidArgumentException('Unknown field type.');
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
        $type = $this->resolveFieldType($item['type'] ?? null);

        if (! $type::isInput()) {
            return $item;
        }

        $field = new $type([...$item, 'key' => filled($item['key'] ?? null) ? $this->normalizeKey($type, (string) $item['key']) : null]);
        $base = $field->getKey();
        $takenKeys = [...$this->getTakenKeys($except), ...$type::reservedKeys()];

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
        $keys = [
            ...array_map(fn (FormField $field): string => $field->getKey(), $this->getFixedInputs()),
            ...$this->getReservedKeys(),
        ];

        foreach ($this->getItems() as $uuid => $item) {
            if ($uuid !== $except && $item::isInput()) {
                $keys[] = $item->getKey();
            }
        }

        return $keys;
    }

    protected function renameConditionKey(string $from, string $to): void
    {
        $items = $this->getRawState() ?? [];

        foreach ($items as $uuid => $item) {
            foreach ($item['conditions'] ?? [] as $index => $condition) {
                if (($condition['key'] ?? null) === $from) {
                    $items[$uuid]['conditions'][$index]['key'] = $to;
                }
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
