<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use BackedEnum;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichEditorTool;

use function Filament\Support\generate_icon_html;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use Livewire\Component as Livewire;
use VanOns\FilamentFormBuilder\Classes\MergeTags;
use VanOns\FilamentFormBuilder\Models\Form;

/**
 * Rich editors that take the merge tags of the form being edited, with the
 * package's picker behind Filament's tag button.
 */
class MergeTagEditor
{
    /**
     * Without the link to the submission where the text reaches a visitor
     * rather than a colleague.
     */
    public static function make(string $name, bool $withAllFields = true, bool $withSubmissionLink = true): RichEditor
    {
        return RichEditor::make($name)
            ->mergeTags(fn (Livewire $livewire, mixed $state): array => static::tags($livewire, $state, $withAllFields, $withSubmissionLink))
            ->tools(fn (Livewire $livewire): RichEditorTool => static::tool($livewire, $withAllFields, $withSubmissionLink))
            ->aboveContent(fn (Livewire $livewire, mixed $state): HtmlString => static::icons($livewire, $state))
            ->extraAttributes(['class' => 'ffb-merge-tag-editor']);
    }

    /**
     * One line of text with merge tags, as tall as a text input, such as a
     * subject.
     */
    public static function line(string $name, bool $withSubmissionLink = true): RichEditor
    {
        return static::make($name, withAllFields: false, withSubmissionLink: $withSubmissionLink)
            ->toolbarButtons(['mergeTags'])
            ->extraAttributes(['class' => 'ffb-merge-tag-line'], merge: true);
    }

    /**
     * The form as it stands on the page, so the tags follow the canvas before
     * it is saved, also from inside a modal.
     */
    public static function form(Livewire $livewire): Form
    {
        return new Form([
            'template' => data_get($livewire, 'data.template'),
            'custom' => data_get($livewire, 'data.custom') ?? [],
        ]);
    }

    /**
     * The tags an editor offers, plus any tag its content holds that the form
     * no longer has, so it still reads as something.
     *
     * @return array<string, string>
     */
    public static function tags(Livewire $livewire, mixed $state, bool $withAllFields = true, bool $withSubmissionLink = true): array
    {
        $tags = static::form($livewire)->getMergeTags($withAllFields, $withSubmissionLink);

        foreach (MergeTags::ids($state) as $id) {
            $tags[$id] ??= __('filament-form-builder::general.merge_tags.missing', ['key' => $id]);
        }

        return $tags;
    }

    /**
     * Filament draws a tag as its label only, without anything to tell the
     * types apart; this gives each tag the icon the picker shows, and marks
     * the ones the form no longer has.
     */
    public static function icons(Livewire $livewire, mixed $state): HtmlString
    {
        $chip = fn (string $id): string => '.ffb-merge-tag-editor span[data-type="mergeTag"][data-id="' . $id . '"]';
        // An id lands inside a style element, so one that could close it is left out.
        $isSafe = fn (string $id): bool => preg_match('/^[\w.-]+$/', $id) === 1;
        $known = [];
        $rules = [];

        foreach (static::form($livewire)->getMergeTagGroups() as $group) {
            foreach ($group['tags'] as $id => $tag) {
                $known[] = (string) $id;
                $svg = generate_icon_html($tag['icon'])?->toHtml();

                if ($svg !== null && $isSafe((string) $id)) {
                    $rules[] = $chip((string) $id) . '{--ffb-tag-icon:url("data:image/svg+xml,' . rawurlencode($svg) . '")}';
                }
            }
        }

        foreach (array_filter(array_diff(MergeTags::ids($state), $known), $isSafe) as $id) {
            $rules[] = $chip($id) . '{--ffb-tag-icon:var(--ffb-tag-icon-missing);background:var(--ffb-tag-missing-surface);box-shadow:inset 0 0 0 1px var(--ffb-tag-missing-ring);color:var(--ffb-tag-missing-text);text-decoration:line-through}';
        }

        return new HtmlString('<style class="ffb-merge-tag-icons">' . implode('', $rules) . '</style>');
    }

    /**
     * Filament's tag button, opening the package's picker instead of the flat
     * list: grouped, with the icon of each field type and a search.
     */
    public static function tool(Livewire $livewire, bool $withAllFields = true, bool $withSubmissionLink = true): RichEditorTool
    {
        $groups = [];
        $icons = [];

        foreach (static::form($livewire)->getMergeTagGroups($withAllFields, $withSubmissionLink) as $group) {
            $tags = [];

            foreach ($group['tags'] as $id => $tag) {
                $icon = $tag['icon'] instanceof BackedEnum ? (string) $tag['icon']->value : $tag['icon'];
                $icons[$icon] ??= generate_icon_html($tag['icon'])?->toHtml() ?? '';
                $tags[] = ['id' => (string) $id, 'label' => $tag['label'], 'icon' => $icon];
            }

            if ($tags !== []) {
                $groups[] = ['label' => $group['label'], 'tags' => $tags];
            }
        }

        $picker = Js::from(['groups' => $groups, 'icons' => $icons]);

        return RichEditorTool::make('mergeTags')
            ->label(__('filament-forms::components.rich_editor.tools.merge_tags'))
            ->icon('fi-o-merge-tag')
            ->iconAlias('forms:components.rich-editor.toolbar.merge-tags')
            ->activeJsExpression('false')
            ->jsHandler("\$dispatch('ffb-merge-tags', { anchor: \$el, insert: (id) => insertMergeTag(id), ...{$picker} })");
    }
}
