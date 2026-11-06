<?php

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
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

// Laravel only sends CommandFinished outside unit tests, so the tests send it themselves.
function outputAfter(string $command): string
{
    event(new CommandFinished($command, new ArrayInput([]), $output = new BufferedOutput(), 0));

    return $output->fetch();
}

it('names the migrations an app has neither published nor run after migrate', function () {
    DB::table('migrations')->delete();

    expect(outputAfter('migrate'))
        ->toContain('filament-form-builder has 4 migrations that are not published')
        ->toContain('create_form_submission_integration_logs_table')
        ->toContain('php artisan vendor:publish --tag=filament-form-builder-migrations')
        ->and(outputAfter('migrate:status'))->toBe('');
});

it('stays quiet once the migrations are published', function () {
    DB::table('migrations')->delete();
    $this->artisan('vendor:publish', ['--tag' => 'filament-form-builder-migrations'])->assertSuccessful();

    expect(outputAfter('migrate'))->toBe('');
});

it('stays quiet about migrations that ran but whose files are gone, as after schema:dump --prune', function () {
    DB::table('migrations')->delete();

    foreach (File::files(__DIR__.'/../../../database/migrations') as $file) {
        DB::table('migrations')->insert([
            'migration' => '2020_01_01_000000_'.preg_replace('/^\\d{4}_\\d{2}_\\d{2}_\\d{6}_/', '', $file->getBasename('.php')),
            'batch' => 1,
        ]);
    }

    expect(outputAfter('migrate'))->toBe('');
});
