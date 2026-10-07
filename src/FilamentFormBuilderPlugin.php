<?php

namespace VanOns\FilamentFormBuilder;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Exceptions\NoDefaultPanelSetException;
use Filament\FilamentManager;
use Filament\Forms\Components\TextInput;
use Filament\Panel;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\CreateForm;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\ViewForm;
use VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource;
use VanOns\FilamentFormBuilder\Models\Form;

class FilamentFormBuilderPlugin implements Plugin
{
    protected static ?Closure $redirectSchemaUsing = null;

    protected static ?Closure $resolveRedirectUrlUsing = null;

    protected string | bool | Closure | null $navigationGroup = null;

    protected bool | Closure | null $hasExportAction = null;

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * The plugin of the panel being served, else of the default panel, else
     * one with the config's defaults.
     */
    public static function get(): static
    {
        try {
            $panel = app(FilamentManager::class)->getCurrentOrDefaultPanel();
        } catch (NoDefaultPanelSetException) {
            $panel = null;
        }

        $plugin = $panel?->getPlugins()['filament-form-builder'] ?? null;

        return $plugin instanceof static ? $plugin : static::make();
    }

    /**
     * Where this panel lists the forms and submissions: true for the
     * package's own group, false for none, or a group of your own.
     */
    public function navigationGroup(string | bool | Closure | null $group = true): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        $group = value($this->navigationGroup ?? config('filament-form-builder.navigation_group', true));

        return match (true) {
            is_string($group) => $group,
            (bool) $group => __('filament-form-builder::general.navigation-group'),
            default => null,
        };
    }

    /**
     * Whether this panel offers the submissions export, a queued Filament
     * export that needs the tables the installation docs list.
     */
    public function exportAction(bool | Closure $condition = true): static
    {
        $this->hasExportAction = $condition;

        return $this;
    }

    public function hasExportAction(): bool
    {
        return (bool) value($this->hasExportAction ?? config('filament-form-builder.export_action', true));
    }

    /**
     * Override the redirect URL branch schema. Default is a single URL TextInput.
     *
     * @param  Closure(): array<Component>  $callback
     */
    public static function redirectSchemaUsing(Closure $callback): void
    {
        static::$redirectSchemaUsing = $callback;
    }

    /**
     * Override how the stored value is resolved into a redirect URL on submit.
     */
    public static function resolveRedirectUrlUsing(Closure $callback): void
    {
        static::$resolveRedirectUrlUsing = $callback;
    }

    /**
     * @return array<Component>
     */
    public static function getRedirectSchema(): array
    {
        if (static::$redirectSchemaUsing !== null) {
            return (static::$redirectSchemaUsing)();
        }

        return [
            TextInput::make('url')
                ->label(__('filament-form-builder::general.redirect_page'))
                ->required()
                ->placeholder(__('filament-form-builder::general.form_redirect_example', ['url' => 'https://example.com/form-confirmation']))
                ->prefixIcon(Heroicon::OutlinedLink)
                ->live(onBlur: true)
                ->columnSpanFull(),
        ];
    }

    public static function resolveRedirectUrl(mixed $stored, Form $form): ?string
    {
        if (static::$resolveRedirectUrlUsing !== null) {
            return (static::$resolveRedirectUrlUsing)($stored, $form);
        }

        return is_string($stored) ? $stored : null;
    }

    public function getId(): string
    {
        return 'filament-form-builder';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            FormResource::class,
            FormSubmissionResource::class,
        ]);

        // One picker for every tag button on the page, so it also opens from a modal.
        $panel->renderHook(
            PanelsRenderHook::BODY_END,
            fn (): View => view('filament-form-builder::filament.merge-tag-picker'),
            scopes: [CreateForm::class, EditForm::class, ViewForm::class],
        );
    }

    public function boot(Panel $panel): void
    {
    }
}
