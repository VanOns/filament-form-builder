<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\Actions\LinkAction;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class ConsentField extends CheckboxField
{
    public static string $view = 'filament-form-builder::components.fields.consent-field';

    public static string $previewView = 'filament-form-builder::filament.previews.consent';

    /**
     * HTML once stored; while the editor is open, its tiptap document.
     *
     * @var string|array<string, mixed>|null
     */
    public string | array | null $text = null;

    public function isRequired(): bool
    {
        return true;
    }

    /**
     * The name in tables, exports and mails; without one, the text itself.
     */
    public function getLabel(): string
    {
        if (filled($this->label)) {
            return $this->label;
        }

        $text = trim(html_entity_decode(strip_tags($this->getCleanText()), ENT_QUOTES | ENT_HTML5));

        return $text !== '' ? $text : static::getTypeLabel();
    }

    public function getTextHtml(): HtmlString
    {
        $text = $this->getCleanText();

        return new HtmlString($text !== '' ? $text : e($this->getLabel()));
    }

    protected function getCleanText(): string
    {
        // A link opens in a new tab, or the visitor loses what they filled in.
        $config = (new HtmlSanitizerConfig())
            ->defaultAction(HtmlSanitizerAction::Block)
            ->dropElement('script')
            ->dropElement('style')
            ->allowElement('a', ['href'])
            ->allowElement('strong')
            ->allowElement('em')
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'target', '_blank')
            ->forceAttribute('a', 'rel', 'noopener');

        // The text sits on one line beside the box, so its paragraphs run on.
        $html = is_array($this->text) ? RichContentRenderer::make($this->text)->toUnsafeHtml() : (string) $this->text;

        return trim((new HtmlSanitizer($config))->sanitize((string) preg_replace('#</p>\s*<p\b[^>]*>#i', ' ', $html)));
    }

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedDocumentCheck;
    }

    /**
     * Consent that was given in advance was never given.
     */
    public static function getDefaultValueComponent(): ?Component
    {
        return null;
    }

    public static function getFields(): array
    {
        return [
            RichEditor::make('text')
                ->label(__('filament-form-builder::fields.consent_text'))
                ->toolbarButtons(['bold', 'italic', 'link'])
                ->minHeight('5rem')
                // Links always open in a new tab, so the link window need not ask.
                ->registerActions([
                    LinkAction::make()->schema([
                        TextInput::make('url')
                            ->label(__('filament-forms::components.rich_editor.actions.link.modal.form.url.label'))
                            ->inputMode('url'),
                        Hidden::make('shouldOpenInNewTab'),
                    ]),
                ])
                ->helperText(__('filament-form-builder::fields.consent_text_helper'))
                ->live(debounce: 500)
                ->required()
                ->columnSpanFull(),
            TextInput::make('label')
                ->label(__('filament-form-builder::fields.consent_name'))
                ->placeholder(__('filament-form-builder::fields.consent_name_placeholder'))
                ->helperText(__('filament-form-builder::fields.consent_name_helper'))
                ->live(onBlur: true),
            TextInput::make('description')
                ->label(__('filament-form-builder::fields.description')),
        ];
    }
}
