<?php

namespace VanOns\FilamentFormBuilder;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\TextInput;
use Filament\Panel;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource;
use VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource;
use VanOns\FilamentFormBuilder\Models\Form;

class FilamentFormBuilderPlugin implements Plugin
{
    protected static ?Closure $redirectFieldUsing = null;

    protected static ?Closure $resolveRedirectUrlUsing = null;

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Override the redirect URL field. Default is a plain URL TextInput.
     */
    public static function redirectFieldUsing(Closure $callback): void
    {
        static::$redirectFieldUsing = $callback;
    }

    /**
     * Override how the stored value is resolved into a redirect URL on submit.
     */
    public static function resolveRedirectUrlUsing(Closure $callback): void
    {
        static::$resolveRedirectUrlUsing = $callback;
    }

    public static function getRedirectField(): Component
    {
        if (static::$redirectFieldUsing !== null) {
            return (static::$redirectFieldUsing)();
        }

        return TextInput::make('submit_notification_content')
            ->label(__('filament-form-builder::general.url'))
            ->required()
            ->placeholder(__('filament-form-builder::general.form_redirect_example', ['url' => 'https://example.com/form-confirmation']))
            ->suffixIcon(Heroicon::OutlinedLink)
            ->columnSpanFull();
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
    }

    public function boot(Panel $panel): void
    {
    }
}
