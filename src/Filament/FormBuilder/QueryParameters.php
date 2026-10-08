<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\TextInput;
use VanOns\FilamentFormBuilder\Classes\MergeTags;

/**
 * The parameters a redirect passes on, one row each: a name and a value of
 * text and merge tags. Stored as the query string text it always was, such
 * as `name={{ $name }}&source=website`.
 */
class QueryParameters extends Repeater
{
    public const NAME_PATTERN = '/^[\w.\-\[\]]+$/';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);
        $this->live();
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
                ->validationMessages(['regex' => __('filament-form-builder::general.query_parameter_invalid')])
                ->extraInputAttributes(['class' => 'ffb-mono-input'])
                ->live(onBlur: true),
            MergeTagEditor::line('value', withSubmissionLink: false)
                ->label(__('filament-form-builder::general.query_value'))
                ->live(onBlur: true),
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
     * The rows a stored query string reads as, its values as one line of the
     * editor with the placeholders as tags, or null when a part of it is no
     * parameter, such as a name without `=`.
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
            [$name, $value] = array_pad(explode('=', trim($parameter), 2), 2, null);

            if ($value === null || !preg_match(static::NAME_PATTERN, $name)) {
                return null;
            }

            $text = MergeTags::mapText($value, rawurldecode(...));

            $rows[] = ['name' => $name, 'value' => $text === '' ? '' : (string) MergeTags::fromLegacy('<p>' . e($text) . '</p>')];
        }

        return $rows;
    }

    /**
     * The rows as a query string: fixed text URL-encoded, tags as the
     * placeholders the submission fills in.
     *
     * @param  array<mixed>  $rows
     */
    public static function build(array $rows): ?string
    {
        $parameters = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $value = MergeTags::mapText(self::toText($row['value'] ?? null), rawurlencode(...));

            $parameters[] = $name . '=' . $value;
        }

        return $parameters === [] ? null : implode('&', $parameters);
    }

    /**
     * The editor's line as text with a `{{ $key }}` for each tag.
     */
    private static function toText(mixed $value): string
    {
        $html = is_array($value) ? RichContentRenderer::make($value)->toUnsafeHtml() : (string) $value;

        $html = (string) preg_replace_callback(
            '/<span\b([^>]*\bdata-type="mergeTag"[^>]*)>.*?<\/span>/s',
            fn (array $span): string => preg_match('/\bdata-id="([^"]*)"/', $span[1], $id) ? MergeTags::placeholder(html_entity_decode($id[1], ENT_QUOTES)) : '',
            $html,
        );

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }
}
