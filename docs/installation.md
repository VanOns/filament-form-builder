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
`php artisan vendor:publish --tag=filament-form-builder-config`. The package
speaks English and Dutch; publish its translations with
`--tag=filament-form-builder-translations` to change a text or add a language.

## What the app needs to run

- **A queue worker.** E-mail notifications, integrations and the export run as
  queued jobs, so without one nothing goes out. An app without a worker sets
  `QUEUE_CONNECTION=sync`, which runs them during the request.
- **The scheduler**, `php artisan schedule:run` every minute. Every night it
  deletes the submissions older than
  [their form keeps them](usage.md#how-long-submissions-are-kept); without it
  they stay.
- **`php artisan filament:assets`** after every update of the package, which
  `filament:upgrade` does, so forms on the site load the current script and
  stylesheet.

## Who sees what

Filament asks a model's policy before it lists, shows, edits or deletes a
record. The package has none, so everyone who can open the panel sees every
form and every submission, with the personal data in them. Laravel does not
find a policy by its name for a package's model, so register them in a service
provider:

```php
use Illuminate\Support\Facades\Gate;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

Gate::policy(Form::class, FormPolicy::class);
Gate::policy(FormSubmission::class, FormSubmissionPolicy::class);
```

Someone who may view a form but not update it gets its page read-only.

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
- Pick a form with `FormSelect` in the block or field that shows one. It
  offers the forms as the site of the record it is on has them: that site's
  copy of a form, or the original while there is none. A page copied to
  another site still holds the id of the original's form; the select turns it
  into the copy's when the page opens, and saving the page stores that. No
  code on the page model is needed:

```php
use VanOns\FilamentFormBuilder\Filament\Forms\Components\FormSelect;

FormSelect::make('form_id')->required()
```

  `Form::idInSite($id, $site)` gives the id of a form's copy on a site, or the
  id itself when there is none, for anywhere else an id is kept.

A copy is never made on its own, so a page can be copied before its form is:
until the form has a copy on that site, the page shows the original.
