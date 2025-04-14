<?php

namespace VanOns\FilamentFormBuilder;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionCreated;
use VanOns\FilamentFormBuilder\Listeners\FormSubmission\SendFormSubmissionNotification;
use VanOns\FilamentFormBuilder\View\Components\Form;

class FilamentFormBuilderProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->hasMigrations();
        $this->hasTranslations();
        $this->hasConfig();
        $this->hasViewComponents();
        $this->hasViews();
        $this->hasRoutes();
        $this->hasRateLimiter();
        $this->hasEvents();
    }

    public function hasMigrations(): void
    {
        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'filament-form-builder-migrations');
    }

    public function hasTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'filament-form-builder');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/filament-form-builder'),
        ], 'filament-form-builder-translations');
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

    public function hasRateLimiter(): void
    {
        RateLimiter::for(
            'filament-form-builder-submissions',
            function (Request $request) {
                $limit = config('filament-form-builder.rate-limit-hour', 60);

                return Limit::perHour($limit)
                    ->by($request->user()?->id ?: $request->ip());
            }
        );
    }

    public function hasEvents(): void
    {
        Event::listen(
            FormSubmissionCreated::class,
            SendFormSubmissionNotification::class,
        );
    }
}
