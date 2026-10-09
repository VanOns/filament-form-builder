# Installation

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

A panel can set its own navigation group, export and field types on the
plugin; without them it takes `navigation_group`, `export_action` and
`without_fields` from the config:

```php
FilamentFormBuilderPlugin::make()
    ->navigationGroup('Website') // false for none, true for the package's own
    ->exportAction(false)
    ->withoutFields(['phone', 'file_upload']); // out of the palette, by their name in `fields`
```

A field type left out stays registered: a form that already has such a field
keeps it, on the canvas and on the site.

Everything else, such as the form types, the uploads and the spam checks, is also
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

## Forms per site

With [filament-multisite](https://github.com/VanOns/filament-multisite), a form
can have a copy per site, linked like a page and its translations: a Dutch
contact form and its English one. It is off until you turn it on, also with
multisite installed. Publish and run its migration, which adds `site` and
`origin_id` to `forms`, and set `multisite` in the config:

```bash
php artisan vendor:publish --tag=filament-form-builder-multisite-migrations
php artisan migrate
```

```php
// config/filament-form-builder.php
'multisite' => true,
```

Turned on without the columns, the forms page says which commands to run.

- The form's page gets multisite's site switcher, in a section beside the
  title: picking another site opens
  the form's copy there, or makes one from the form, titled with the site's
  short name after it ("Contact (EN)") as titles are unique. Everything an
  editor writes is the copy's own; the type and how long submissions are kept
  follow the original. The forms table gets a site column and filter.
- `<x-render-form :form="$form" />` shows the copy for the site the page is on,
  and the form itself while that site has none. A front end of your own does
  the same with `$form->inCurrentSite()`.
- A page copied to another site still holds the id of the original's form.
  To put the copy's id in, map it in the page model's `modifyClonedAttributes()`
  with `Form::idInSite($id, $site)`, which gives the id of the form's copy on
  that site, or the id itself when there is none:

```php
public function modifyClonedAttributes(array $attributes, Multisitable $origin, string $site): array
{
    foreach ($attributes['content'] ?? [] as $index => $block) {
        if (($block['type'] ?? null) === 'form') {
            $attributes['content'][$index]['data']['form_id'] = Form::idInSite($block['data']['form_id'] ?? null, $site);
        }
    }

    return $attributes;
}
```

A copy is never made on its own, so a page can be copied before its form is:
until the form has a copy on that site, the page shows the original.
