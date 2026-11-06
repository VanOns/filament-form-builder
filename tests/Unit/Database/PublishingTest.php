<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use VanOns\FilamentFormBuilder\FilamentFormBuilderProvider;

function appMigrations(): array
{
    return collect(File::glob(database_path('migrations/*.php')))
        ->map(fn (string $path): string => basename($path))
        ->reject(fn (string $name): bool => str_starts_with($name, '0001_01_01_'))
        ->sort()
        ->values()
        ->all();
}

afterEach(function () {
    foreach (appMigrations() as $name) {
        File::delete(database_path("migrations/{$name}"));
    }
});

it('publishes the upgrade and multisite migrations once, also when the app published one before', function () {
    File::copy(
        __DIR__.'/../../../database/multisite/2026_10_09_000000_add_sites_to_forms_table.php',
        database_path('migrations/2026_10_20_120000_add_sites_to_forms_table.php'),
    );
    $this->refreshApplication();

    foreach (range(1, 2) as $run) {
        $this->artisan('vendor:publish', ['--tag' => 'filament-form-builder-multisite-migrations'])->assertSuccessful();
        $this->artisan('vendor:publish', ['--tag' => 'filament-form-builder-upgrade-migrations'])->assertSuccessful();
    }

    expect(appMigrations())->toHaveCount(2)
        ->and(appMigrations()[0])->toBe('2026_10_20_120000_add_sites_to_forms_table.php')
        ->and(appMigrations()[1])->toEndWith('_upgrade_filament_form_builder_tables_to_v3.php');
});

it('puts the multisite migration after the create migrations, also when both are published at once', function () {
    $this->artisan('vendor:publish', [
        '--tag' => ['filament-form-builder-migrations', 'filament-form-builder-multisite-migrations'],
    ])->assertSuccessful();

    expect(appMigrations())->toHaveCount(5)
        ->and(last(appMigrations()))->toEndWith('_add_sites_to_forms_table.php');
});

it('publishes the translations under the v2 tag as well', function () {
    expect(ServiceProvider::pathsToPublish(FilamentFormBuilderProvider::class, 'filament-form-builder-translations'))
        ->not->toBeEmpty()
        ->toBe(ServiceProvider::pathsToPublish(FilamentFormBuilderProvider::class, 'filament-form-builder-lang'));
});
