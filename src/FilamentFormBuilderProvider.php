<?php

namespace VanOns\FilamentFormBuilder;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use VanOns\FilamentFormBuilder\View\Components\Form;

class FilamentFormBuilderProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        // discoversMigrations() auto-finds every file in database/migrations and,
        // on publish, strips the timestamp prefix to match any already-published
        // migration by name, reusing that file instead of creating a duplicate.
        // This is what fixes `vendor:publish` recreating all migrations each run.
        $package
            ->name('filament-form-builder')
            ->discoversMigrations();
    }

    public function packageBooted(): void
    {
        $this->hasTranslations();
        $this->hasUpgradeMigration();
        $this->hasConfig();
        $this->hasViewComponents();
        $this->hasViews();
        $this->hasRoutes();
        $this->hasRateLimiter();
        $this->hasAssets();
    }

    public function hasTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'filament-form-builder');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/filament-form-builder'),
        ], 'filament-form-builder-translations');
    }

    // Kept out of database/migrations so a new install, which gets the v3 schema
    // from the create migrations, never runs it.
    public function hasUpgradeMigration(): void
    {
        $this->publishesMigrations([
            __DIR__.'/../database/upgrades' => database_path('migrations'),
        ], 'filament-form-builder-upgrade-migrations');
    }

    public function hasConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/general.php', 'filament-form-builder');

        $this->publishes([
            __DIR__.'/../config/general.php' => config_path('filament-form-builder.php'),
        ], 'filament-form-builder-config');
    }

    public function hasViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-form-builder');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/filament-form-builder'),
        ], 'filament-form-builder-views');
    }

    public function hasViewComponents(): void
    {
        Blade::component('render-form', Form::class);
    }

    public function hasRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

    public function hasAssets(): void
    {
        FilamentAsset::register([
            Css::make('form-canvas', __DIR__.'/../resources/css/form-canvas.css'),
            Css::make('form-submission', __DIR__.'/../resources/css/form-submission.css'),
            AlpineComponent::make('form-canvas', __DIR__.'/../resources/js/components/form-canvas.js'),
            AlpineComponent::make('merge-tag-picker', __DIR__.'/../resources/js/components/merge-tag-picker.js'),
            AlpineComponent::make('recipients-input', __DIR__.'/../resources/js/components/recipients-input.js'),
        ], 'van-ons/filament-form-builder');
    }

    public function hasRateLimiter(): void
    {
        RateLimiter::for(
            'filament-form-builder-submissions',
            function (Request $request) {
                $limit = config('filament-form-builder.rate_limit_per_hour', 60);

                return Limit::perHour($limit)
                    ->by($request->user()?->id ?: $request->ip());
            }
        );
    }
}
