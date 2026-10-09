<p align="center" class="filament-hidden"><img src="art/social-card.png" alt="Social card of Filament Form Builder"></p>

# Filament Form Builder

[![Latest version on GitHub](https://img.shields.io/github/release/VanOns/filament-form-builder.svg?style=flat-square)](https://github.com/VanOns/filament-form-builder/releases)
[![Total downloads](https://img.shields.io/packagist/dt/van-ons/filament-form-builder.svg?style=flat-square)](https://packagist.org/packages/van-ons/filament-form-builder)
[![GitHub issues](https://img.shields.io/github/issues/VanOns/filament-form-builder?style=flat-square)](https://github.com/VanOns/filament-form-builder/issues)
[![License](https://img.shields.io/github/license/VanOns/filament-form-builder?style=flat-square)](https://github.com/VanOns/filament-form-builder/blob/main/LICENSE.md)
[![Plumb score](https://img.shields.io/badge/dynamic/regex?url=https%3A%2F%2Fplumbphp.dev%2Fbadges%2Fvan-ons%2Ffilament-form-builder%2Fcomposite.svg&search=%3Ctitle%3Eplumb%3A%5Cs%2A%28%5B%5E%3C%5D%2B%29%3C&replace=%241&label=plumb&style=flat-square)](https://plumbphp.dev/van-ons/filament-form-builder)

Add a customizable form builder to your Filament admin panel.

## Quick start

> For Filament version compatibility, see [Compatibility](docs/compatibility.md).

### Installation

Start by installing the package via Composer:

```bash
composer require van-ons/filament-form-builder:^3.0
```

Next, publish and run the migrations:

```bash
php artisan vendor:publish --tag=filament-form-builder-migrations
php artisan migrate
```

Then publish the assets: the builder's own, and the script and stylesheet that
forms on the site load. A Filament app usually does this on every
`composer update` through `php artisan filament:upgrade`.

```bash
php artisan filament:assets
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

### Exporting submissions

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

### What the app needs to run

- **A queue worker.** E-mail notifications, integrations and the export run as
  queued jobs, so without one nothing goes out. An app without a worker sets
  `QUEUE_CONNECTION=sync`, which runs them during the request.
- **The scheduler**, `php artisan schedule:run` every minute. Every night it
  deletes the submissions older than
  [their form keeps them](docs/usage.md#how-long-submissions-are-kept); without
  it they stay.
- **`php artisan filament:assets`** after every update of the package, which
  `filament:upgrade` does, so forms on the site load the current script and
  stylesheet.

Everyone who can open the panel sees every form and submission until the app
registers policies for them, see
[Who sees what](docs/installation.md#who-sees-what).

### Showing a form

`<x-render-form :form="$form" />` shows a form on a page. An editor picks one,
for instance in a page block, with `FormSelect::make('form_id')` from
`VanOns\FilamentFormBuilder\Filament\Forms\Components`. See
[Showing a form](docs/usage.md#showing-a-form) for its view, its route and its
middleware.

### A front end in React, Vue or Inertia

`<x-render-form>` shows a form with its conditional fields working. A front end
of your own uses the same rules through `resources/js/conditions.js`: hand it
`$form->getFieldConditions()` and the answers so far, and `hiddenKeys()` says
which fields to hide, exactly as the server reads them. `resources/js/honeypot.js`
sets the spam traps the server expects from `$form->getHoneypot()`. See
[In a front end of your own](docs/usage.md#in-a-front-end-of-your-own) for the
import alias and an Inertia example.

## Documentation

Please see the [documentation](docs) for detailed information about installation and usage.

## Contributing

Please see [Contributing](CONTRIBUTING.md) for more information about how you can contribute.

## Testing

```bash
composer test
```

## Changelog

Please see [Changelog](CHANGELOG.md) for more information about what has changed recently.

## Upgrading

Please see [Upgrading](UPGRADING.md) for more information about how to upgrade.

## Security

Please see [Security](SECURITY.md) for more information about how we deal with security.

## Credits

We would like to thank the following contributors for their contributions to this project:

- [All contributors](../../contributors)

## License

The scripts and documentation in this project are released under the [MIT License](LICENSE.md).

---

<p align="center"><a href="https://van-ons.nl/" target="_blank"><img src="https://opensource.van-ons.nl/files/cow.png" width="50" alt="Logo of Van Ons"></a></p>
