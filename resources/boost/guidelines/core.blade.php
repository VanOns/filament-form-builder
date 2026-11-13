## Filament Form Builder v3 (`van-ons/filament-form-builder`)

A Filament plugin in which editors build forms on a canvas (fields, widths, conditions, steps), set up e-mail notifications, what a visitor sees after sending, and integrations that pass submissions on; plus a Blade component that shows a form on the site and a route that stores what visitors send. Developers add form types with fields in code and field types of their own. Requires Filament 4.13+ or 5.8+ and Laravel 12 or 13. The service provider is auto-discovered. Still on v2? Use the `filament-form-builder-v3-upgrade` skill.

### Setup

@verbatim
<code-snippet name="Install" lang="bash">
composer require van-ons/filament-form-builder:^3.0
php artisan vendor:publish --tag=filament-form-builder-migrations
php artisan migrate
php artisan filament:assets   # builder assets + the script and stylesheet forms on the site load
</code-snippet>

<code-snippet name="Register the plugin" lang="php">
use VanOns\FilamentFormBuilder\FilamentFormBuilderPlugin;

return $panel->plugin(
    FilamentFormBuilderPlugin::make()
        ->navigationGroup('Website')        // false for none, true (default) for the package's own
        ->exportAction(false)               // the queued CSV/Excel export, on by default
        ->withoutFields(['phone'])          // out of the palette, by their name in `fields`
);
</code-snippet>
@endverbatim

Publish tags: `filament-form-builder-migrations`, `filament-form-builder-config` (to `config/filament-form-builder.php`), `filament-form-builder-views`, `filament-form-builder-lang`, plus `filament-form-builder-multisite-migrations` and, for a v2 upgrade only, `filament-form-builder-upgrade-migrations`.

What the app needs to run:
- **A queue worker.** E-mail notifications (`SendFormNotificationJob`), integrations (`RunFormIntegrationJob`) and the export are queued; without a worker set `QUEUE_CONNECTION=sync`.
- **The scheduler.** The package schedules `model:prune` for `FormSubmission` daily, which applies `retention_months`.
- **`php artisan filament:assets` after every package update** (`filament:upgrade` does it).
- **Policies.** The package has none, so everyone with panel access sees every form and submission. Register `Gate::policy(Form::class, ...)` and `Gate::policy(FormSubmission::class, ...)` in a service provider.
- **For the export:** database notifications on the panel (`->databaseNotifications()`) and the `notifications`, `exports` and `job_batches` tables (`php artisan make:notifications-table`, `php artisan vendor:publish --tag=filament-actions-migrations`).

### Showing a form

@verbatim
<code-snippet name="Pick and render a form" lang="php">
use VanOns\FilamentFormBuilder\Filament\Forms\Components\FormSelect;

FormSelect::make('form_id')->required(); // e.g. in a page block; stores the form id
</code-snippet>

<code-snippet name="Blade" lang="blade">
<x-render-form :form="$form" />                          {{-- null renders nothing --}}
<x-render-form :form="$form" view="forms.vacancy" />     {{-- another view for this form --}}
</code-snippet>
@endverbatim

@verbatim
- The form posts to `POST /filament-form-builder/{formId}/submit` (route `filament-form-builder.form.store`) through `throttle:filament-form-builder-submissions`, Precognition and `form_middleware` (`['web']`). Add the middleware the site's pages need too, such as the one that sets the locale.
- The response is a **303**: to the outcome's URL, or `back()` with `submit_notification_type` and `submit_notification_content` flashed. An Inertia request whose redirect goes to another host gets a **409** with `X-Inertia-Location`. An expired session (419) goes back with the input and `filament_form_builder_expired` flashed.
- A form type's `response()` replaces this when it returns something.
- A custom `components/form` view keeps `<x-filament-form-builder::honeypot :form="$form" />` and, when `$form->hasSteps()`, `<x-filament-form-builder::steps :form="$form" />`. Forms get `ffb-*` classes; `styles` => `false` drops the package stylesheet.
- A React/Vue/Inertia front end posts to the same route and uses `resources/js/conditions.js`, `steps.js` and `honeypot.js` from the package, fed with `$form->getFieldConditions()`, `$form->getStepKeys()` and `$form->getHoneypot()`. Without the honeypot fields the server rejects the form (error on `ffb_token`).
@endverbatim

### Form types

The `types` config maps the name stored in `forms.template` to a class extending `VanOns\FilamentFormBuilder\Forms\FormType` (`custom` and `contact` are built in). Fields are built with the classes in `VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields`; `CustomFields::make()` marks where the editor's own fields go, and a type without it has no canvas.

@verbatim
<code-snippet name="A form type" lang="php">
namespace App\Forms;

use VanOns\FilamentFormBuilder\Enums\FieldWidth;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;
use VanOns\FilamentFormBuilder\Forms\CustomFields;
use VanOns\FilamentFormBuilder\Forms\FormType;

class VacancyApplication extends FormType
{
    public static function getLabel(): string
    {
        return 'Application';
    }

    public function fields(): array
    {
        return [
            Fields\TextInputField::make('first_name')->label('First name')->required()->span(FieldWidth::HALF),
            Fields\EmailField::make('email')->label('E-mail')->required()->span(FieldWidth::HALF),
            Fields\TextInputField::make('vacancy')->hidden()->defaultFromQuery('vacancy'),
            Fields\RadioField::make('hours')->label('Hours')->options(['full' => 'Full-time', 'part' => 'Part-time']),
            Fields\StepField::make('motivation_step')->title('Motivation'),
            CustomFields::make(),
            Fields\CheckboxField::make('privacy')->label('I agree to the privacy statement')->required(),
            Fields\SubmitField::make('submit')->label('Send'),
        ];
    }

    public function extraValues(): array
    {
        return ['received_from' => 'Received from'];
    }

    public function beforeStore(array $data): array
    {
        return [...$data, 'received_from' => request()->headers->get('referer')];
    }
}
</code-snippet>
@endverbatim

- Hooks are instance methods with the form in `$this->form`: `beforeValidation(array $data)`, `beforeStore(array $data)`, `formatValues(array $values, FormSubmission $submission)`, `afterSubmission(FormSubmission $submission)` (call the parent: it queues the notifications and integrations), `response(FormSubmission $submission)`.
- Other overrides: `settings()` and `messages()`, `rules()` (rules by field key, added after the field's own: for a field an editor built or a rule about several fields; checked per step too, and left out for a key the form has no field for), `extraValues()` (key => label for values `beforeStore()` adds; visitors can only post field keys), `stepSettings()` (`StepSettings::normalize(['title' => ..., 'progress' => 'steps'|'bar'|'none', 'previous' => ..., 'next' => ...])`), `validatesSteps()`, `hasHoneypot()`, `hasNotifications()`, `hasIntegrations()`, `hasRedirect()`, `hasNotificationMessage()`, `hasSubmitNotificationQuery()`, `modifySubmitNotification(FormSubmission $submission)` (sets `$this->redirectUrl` / `$this->notificationMessage`).
- Field setters: `make($key)`, `label()`, `description()`, `required()`, `rules([...])`, `span(FieldWidth::*)`, `hidden()`, `default()`, `defaultFromQuery()`, `conditions([['key' => ..., 'operator' => 'equals', 'value' => ...]], 'all'|'any')`, `editable(false|[...])`, `showColumn()`, `columnLabel()`, `formatAnswerUsing(fn ($value, FormSubmission $submission) => ...)`, `answerView()`, `settings([...])`; per type `placeholder()`, `options()` (value => label), `multiple()` (uploads), `rows()`, `min()`, `max()` and `decimals()` (number; whole numbers without `decimals()`), `title()` (title and step), `text()` (text block).
- Plugin-wide additions: `->formSettings(fn (?FormType $type): array => [...])` (read `$form->settings[...]`), `->fieldSettings(fn (string $type): array => [...])` (read `$field->getSetting(...)`), and, in a service provider's `boot()`, `FilamentFormBuilderPlugin::mergeTag($key, label: ..., value: fn (FormSubmission $submission): string => ...)`, `::redirectSchemaUsing()` and `::resolveRedirectUrlUsing()`.
- A field type of your own extends a class in `Fields` and is added to the `fields` config under a name; stored forms only know that name.

### Integrations

An integration extends `VanOns\FilamentFormBuilder\Classes\Integration` and is listed in the `integrations` config; `Integrations\WebhookIntegration` is built in. Statics: `label()`, `description()`, `icon()`, `schema()` (its settings; `secretInput()` stores encrypted), `fields()` (`MappedField::make($key, $label)`, with `->required()`, `->email()` for e-mail fields only or `->text()` for text with merge tags), `summary()`, `hasConditions()`, `isTestable()`. In `handle()`: `$this->formSubmission`, `setting()`, `secret()`, `mapped()` / `mapped(raw: true)`, `setResponse()`. An exception is retried (3 tries, after 10 and 60 seconds); `$this->fail($message)` fails for good. Settings cannot be named `id`, `class`, `enabled`, `mapping`, `conditions` or `conditionMatch`.

### Config (`config/filament-form-builder.php`)

`types`, `fields`, `integrations`, `webhook.allow_private_network`, `layout` (`flexible`, `two_columns`, `full_width`), `field_conditions`, `email_notifications`, `validate_steps` (server check of each step through Precognition before "Next", for every type; a type overrides with `validatesSteps()`), `multisite`, `redirect_query`, `styles`, `form_middleware`, `rate_limit_per_hour`, `max_length.text` / `textarea`, `submission_meta` (`ip`: `false`, `'anonymized'` or `'full'`), `retention_months`, `uploads.disk` / `max_size` / `extensions` / `link_days` / `attach_max_size` / `middleware`, `honeypot.enabled` / `field` / `min_seconds`, `duplicate_seconds`, `recaptcha` and `turnstile` (env `RECAPTCHA_ENABLED`/`_KEY`/`_SECRET`, `TURNSTILE_ENABLED`/`_KEY`/`_SECRET`), `navigation_group`, `without_fields`, `export_action`.

### Conventions and gotchas

@verbatim
- **Notifications and integrations only run for a visitor's submission** (`afterSubmission()`). A `FormSubmission` created in code, by a seeder or an import, triggers neither. To react to every visitor's submission listen for `Events\FormSubmission\FormSubmitted` (`$event->formSubmission`); `FormSubmissionCreated` fires for every stored submission.
- A honeypot hit or a form sent within `honeypot.min_seconds` gets the normal response but nothing is stored, mailed or sent on. The same answers from the same visitor within `duplicate_seconds` are stored once.
- Field keys are stored as given, without `.`, `*`, `[`, `]` or spaces (and with no `key_` prefix as in v2). A key in a type's `fields()` or `extraValues()` is reserved; an editor's field with the same key is left out of the form.
- Uploads are kept in the submission's `files` column on `uploads.disk` (`local`), served through signed links valid for `uploads.link_days`.
- `validate_steps` only matters for forms in steps (`StepField`). A Precognition request to a type that does not validate steps gets a 403, and step checks have their own limit of 60 a minute per visitor.
- **Multisite** (`van-ons/filament-multisite`): publish and run `filament-form-builder-multisite-migrations` (adds `site` and `origin_id` to `forms`), then set `multisite` to `true`. `<x-render-form>` then shows the current site's copy (`$form->inCurrentSite()`), `FormSelect` offers the forms of the record's site, and `Form::idInSite($id, $site)` maps an id elsewhere.
- OR groups in the submissions filter need `payload.max_nesting_depth` of 13 in `config/livewire.php` (default 10).
- `$form->getType()` gives the form type instance and `$form->getFields()` the fields. A submission keeps the raw answers in `data` (uploads in `files`); `$submission->getValues()` gives them as shown (choice labels, file links, after `formatValues()`), `getFormattedData()` as text without the empty ones.
@endverbatim
