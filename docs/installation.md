# Installation

Start by installing the package via Composer:

```bash
composer require van-ons/filament-form-builder:^2.0
```

Next, publish and run the migrations:

```bash
php artisan vendor:publish --tag=filament-form-builder-migrations
php artisan migrate
```

Finally, add the plugin to your Filament panel provider:

```php
use Filament\Panel;
use Filament\PanelProvider;
use VanOns\FilamentFormBuilder\FilamentFormBuilderPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->plugin(FilamentFormBuilderPlugin::make());
    }
}
```

A panel can set its own navigation group and export on the plugin; without
them it takes `navigation_group` and `export_action` from the config:

```php
FilamentFormBuilderPlugin::make()
    ->navigationGroup('Website') // false for none, true for the package's own
    ->exportAction(false);
```

Everything else, such as the form types, the uploads and reCAPTCHA, is also
used outside the panel, where a visitor sends a form or a job sends its mails,
so it stays in the config. Publish it with
`php artisan vendor:publish --tag=filament-form-builder-config`.

## Exporting submissions

The submissions tables can export to CSV and Excel. Filament runs an export in
the queue and sends the download link as a database notification, so the app
needs a queue worker, the `job_batches`, `notifications` and `exports` tables,
and database notifications in the panel:

```bash
php artisan make:notifications-table
php artisan vendor:publish --tag=filament-actions-migrations
php artisan migrate
```

```php
return $panel
    ->plugin(FilamentFormBuilderPlugin::make())
    ->databaseNotifications();
```

Laravel's default jobs migration already creates `job_batches`; an app without
it adds it with `php artisan make:queue-batches-table`. To leave the export out
of a panel, call `->exportAction(false)` on the plugin; `export_action` in the
config sets it for every panel.
