<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Livewire\Component as Livewire;

/**
 * The parameters a redirect passes on, one row each: a name and the answer
 * it carries. Stored as the query string text it always was, such as
 * `name={{ $name }}&form={{ $form_title }}`.
 */
class QueryParameters extends Repeater
{
    public const NAME_PATTERN = '/^[\w.\-\[\]]+$/';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);
        $this->reorderable(false);
        $this->addActionLabel(__('filament-form-builder::general.query_add'));

        $this->table([
            TableColumn::make(__('filament-form-builder::general.query_parameter'))->width('40%'),
            TableColumn::make(__('filament-form-builder::general.query_value')),
        ]);

        $this->schema([
            TextInput::make('name')
                ->label(__('filament-form-builder::general.query_parameter'))
                ->required()
                ->regex(static::NAME_PATTERN)
                ->validationMessages(['regex' => __('filament-form-builder::general.query_parameter_invalid')]),
            Select::make('value')
                ->label(__('filament-form-builder::general.query_value'))
                ->options(function (Livewire $livewire, ?string $state): array {
                    $tags = MergeTagEditor::form($livewire)->getMergeTags(withAllFields: false, withSubmissionLink: false);

                    if (filled($state) && !isset($tags[$state])) {
                        $tags[$state] = __('filament-form-builder::general.merge_tags.missing', ['key' => $state]);
                    }

                    return $tags;
                })
                ->required(),
        ]);

        $this->mutateDehydratedStateUsing(static fn (QueryParameters $component, ?array $state): ?string => static::build($component->dehydrateItems($state)));
    }

    /**
     * The stored text becomes rows before the repeater builds its items from
     * them.
     *
     * @param  array<string, mixed>|null  $hydratedDefaultState
     * @param  array<string, true>  $appliedStateCastPaths
     */
    public function hydrateState(?array &$hydratedDefaultState, bool $shouldCallHydrationHooks = true, bool $shouldApplyStateCasts = true, array &$appliedStateCastPaths = []): void
    {
        $rawState = $this->getRawState();

        if (!is_array($rawState)) {
            $this->rawState(static::parse(is_string($rawState) ? $rawState : null) ?? []);
        }

        parent::hydrateState($hydratedDefaultState, $shouldCallHydrationHooks, $shouldApplyStateCasts, $appliedStateCastPaths);
    }

    /**
     * The rows a stored query string reads as, or null when it holds more than
     * parameters filled with one tag each, such as a fixed value.
     *
     * @return list<array{name: string, value: string}>|null
     */
    public static function parse(?string $query): ?array
    {
        $query = trim((string) $query, "?& \t\n\r\0\x0B");

        if ($query === '') {
            return [];
        }

        $rows = [];

        foreach (explode('&', $query) as $parameter) {
            if (!preg_match('/^([\w.\-\[\]]+)=\{\{\s*\$([^\s{}]+)\s*\}\}$/u', trim($parameter), $match)) {
                return null;
            }

            $rows[] = ['name' => $match[1], 'value' => $match[2]];
        }

        return $rows;
    }

    /**
     * @param  array<mixed>  $rows
     */
    public static function build(array $rows): ?string
    {
        $parameters = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $value = (string) ($row['value'] ?? '');

            if ($name !== '' && $value !== '') {
                $parameters[] = $name . '={{ $' . $value . ' }}';
            }
        }

        return $parameters === [] ? null : implode('&', $parameters);
    }
}
