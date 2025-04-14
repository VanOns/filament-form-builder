<?php

namespace VanOns\FilamentFormBuilder;

use Filament\Contracts\Plugin;
use Filament\Panel;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource;
use VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource;

class FilamentFormBuilderPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
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
