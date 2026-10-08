# Upgrading

We aim to make upgrading between versions as smooth as possible, but sometimes it involves specific steps to be taken.
This document will outline those steps. And as much as we try to cover all cases, we might miss some. If you come
across such a case, please let us know by [opening an issue](https://github.com/VanOns/filament-form-builder/issues), or by
adding it yourself and creating a pull request.

## v2 to v3

v3 rebuilds how forms are defined and edited. It has no compatibility layer: templates, the field
classes and the stored canvas data all changed, and forms stored with v2 need converting (see
[Stored forms and submissions](#stored-forms-and-submissions)).

**Let an AI agent do it.** v3 ships a [Laravel Boost](https://github.com/laravel/boost) skill,
`filament-form-builder-v3-upgrade`, that walks an agent through this guide and converts the forms,
notifications and submissions v2 stored. After `composer require van-ons/filament-form-builder:^3.0`, run
`php artisan boost:update` (or `boost:install`) and ask the agent to upgrade the form builder. Boost only adds
a package it has not seen before when you pick it from the question it asks, so choose
`van-ons/filament-form-builder` there; where nobody can answer, such as an agent running the command, add it
to `packages` in `boost.json` first. Its conversion migration is
`vendor/van-ons/filament-form-builder/resources/boost/skills/filament-form-builder-v3-upgrade/references/convert-v2-forms.php`.
Take a database backup first: it rewrites stored data and cannot be rolled back.

### Installing

* v3 needs Filament 4.13 or 5.8 at least. Laravel 11 to 13 and PHP 8.2 and up stay supported.
* Upgrade to v2.9 and run its migrations first. Then publish the one upgrade migration with
  `php artisan vendor:publish --tag=filament-form-builder-upgrade-migrations` and run
  `php artisan migrate`. It adds `form_submissions.files`, `form_submissions.field_snapshot`,
  `form_submissions.source_url` (the page a form was sent from), `form_submissions.meta` (browser,
  language, signed-in user, campaign), `form_submissions.read_at`
  (existing submissions start out read), `form_submission_notification_logs.notification_id`,
  `forms.retention_months` (how long a form keeps its submissions), drops `form_submissions.submitter_email`, and
  moves what a form does after a submission into a list, see below. The regular migrations are now
  three create migrations with the whole v3 schema, for new installs: your published copies of them
  are already run.
* `forms.template` now holds the name a form type is registered under instead of a template class: set
  it to `custom`, `contact` or the name of your own type.
* Republish the config, or compare yours with [`config/general.php`](config/general.php).
* Run `php artisan filament:assets`: the canvas brings its own stylesheet and Alpine component, and
  forms on the site load `form-builder.js` and a minimal stylesheet from it. Remove a script tag of
  your own for `form-builder.js`, and set `styles` to `false` if you style forms entirely yourself.
* Republish any views you published. `components/custom-form-renderer` is now `components/form`,
  `components/forms/contact-form` is gone and `components/fields/hidden-field` and `components/field-error`
  are new. The field views changed: a `div` wraps each field (a `fieldset` for choices) with a
  `label for` inside, errors sit inside that wrapper, and everything has an `ffb-*` class, see
  [Styling forms on the site](docs/usage.md#styling-forms-on-the-site).
* Integrations run from `RunFormIntegrationsJob`, so they need a queue worker unless the queue is `sync`.
* Forms on the site set a honeypot, on by default. A view of your own for `<x-render-form>` adds
  `<x-filament-form-builder::honeypot :form="$form" />`, and a front end of your own (Inertia, React, Vue)
  has to send its two fields, or the server turns the form away; see
  [In a front end of your own](docs/usage.md#in-a-front-end-of-your-own). `honeypot.enabled` turns it off,
  and `hasHoneypot()` for one form type.
* A front end of your own only renders the field types it knows, while editors can pick every type, also the
  ones v3 adds: `step`, `date`, `consent` and `turnstile`. Keep those out of the palette with
  `withoutFields()` on the plugin, or `without_fields` in the config, until it renders them.

### Config

| v2                                           | v3                                                                                    |
|----------------------------------------------|---------------------------------------------------------------------------------------|
| `templates`: class => label                  | `types`: name => class                                                                |
| `fields`: a list of classes                  | `fields`: name => class                                                               |
| `field_visibility_settings`                  | `field_conditions`                                                                    |
| `columns`                                    | Removed, every form has a 12-column grid                                              |
| `field_column_settings`                      | Removed, every field has a column span                                                |
| `email_notification_enabled`: `false`        | `email_notifications`: `true`                                                         |
| `submit_notification_query_enabled`          | `redirect_query`                                                                      |
| `rate-limit-hour`                            | `rate_limit_per_hour`                                                                 |
| `form-middleware`                            | `form_middleware`                                                                     |
| `form-uploads-disk`: `private`               | `uploads.disk`: `local`                                                               |
| `form-uploads-max-size`                      | `uploads.max_size`                                                                    |
| `form-uploads-middleware`: `['web', 'auth']` | `uploads.middleware`: `[]`, the download links are signed                             |
|                                              | `uploads.link_days`: how long a link stays valid                                      |
|                                              | `uploads.attach_max_size`: how many kilobytes of uploads a notification attaches      |
| `add_nav_group`                              | `navigation_group`, or `->navigationGroup()` on the plugin per panel                  |
|                                              | `without_fields`, or `->withoutFields()` on the plugin per panel: types out of the palette |
|                                              | `fields` gains `date`, `consent` and `step`                                           |
|                                              | `styles`: the minimal stylesheet forms on the site load                               |
|                                              | `submission_meta`: what a submission keeps about where it came from                   |
|                                              | `retention_months`: how long submissions are kept                                     |
|                                              | `honeypot`, `duplicate_seconds`: spam traps and double clicks, see [Spam](docs/usage.md#spam) |
|                                              | `turnstile`: Cloudflare Turnstile, beside `recaptcha`; `fields` gains `turnstile`     |
| `enable_export_action`: `false`              | `export_action`: `true`, or `->exportAction()` on the plugin per panel, see [Exporting submissions](docs/installation.md#exporting-submissions) |

### Templates become form types

A template extended `FormComponent` or `PlainForm`; a form type extends
`VanOns\FilamentFormBuilder\Forms\FormType`, is registered under a name in `types`, and has its fields in
code. See [Form types](docs/usage.md#form-types).

* Replace `rules()`, `attributes()` and `placeholders()` with `fields()`, built with the field classes:
  `TextInputField::make('name')->label('Name')->required()->rules(['max:255'])`. A value `beforeStore()`
  adds itself goes in `extraValues()`.
* Replace `isCustom()` with `CustomFields::make()` in `fields()`, where the editor's fields should go.
* Replace `render()` and the template's view: every form renders through `components/form`. Pass
  another view with `<x-render-form :form="$form" view="forms.vacancy" />`.
* Replace `hasRecaptcha()` with `RecaptchaField::make('g-recaptcha-response')` in `fields()`.
* The hooks are instance methods now, with the form in `$this->form`:

| v2 (static)                                               | v3                                                         |
|-----------------------------------------------------------|------------------------------------------------------------|
| `modifyDataBeforeValidation(array $data, Form $form)`     | `beforeValidation(array $data)`                            |
| `modifyDataUsing(array $data, Form $form)`                | `beforeStore(array $data)`                                 |
| `modifyDataValues(array $data, FormSubmission $s)`        | `formatValues(array $values, FormSubmission $s)`           |
| `modifyResourceDataUsing(array $data, FormSubmission $s)` | gone: `formatAnswerUsing()` or `answerView()` on the field |
| `afterSubmissionCreated(FormSubmission $s)`               | `afterSubmission(FormSubmission $s)`                       |
| `successResponse(FormSubmission $s)`                      | `response(FormSubmission $s)`                              |

`settings()`, `messages()`, `hasRedirect()`, `hasNotificationMessage()`,
`hasSubmitNotificationQuery()`, `hasNotifications()` and `hasIntegrations()` keep their names.

* On the model, `getFormComponent()` is `getType()`; `isCustom()`, `getCustomFormRules()`,
  `getCustomFormLabels()` and `getFormAttributes()` give way to `getRules()` and `getSubmissionFields()`.
* Notifications and integrations only run for a visitor's submission, no longer for every created
  `FormSubmission`.
* `submitter_email` is gone from the submission, the table, the export and the placeholders. Use the
  e-mail field's own key instead: its merge tag in a mail, `email` as a receiver.

### Fields

* `InputField` is split into `TextInputField`, `EmailField`, `PhoneField` and `NumberField`, and
  `SelectField` into `RadioField`, `CheckboxListField` and `DropdownField`.
* Keys no longer get a `key_` prefix: a field labelled "Voornaam" posts and stores `voornaam`.
* `large` and `column_start` are gone. A field only has `column_span`, now in columns of a fixed
  12-column grid, limited to a quarter (3), a third (4), a half (6), two thirds (8), three quarters (9)
  or the full row (12). `columns()` on a template is gone with the config value, and the form wrapper no
  longer carries `data-form-builder-columns`; see [Field widths](docs/usage.md#field-widths) for the CSS.
* `visibleWhenKey`, `visibleWhenType` and `visibleWhenValue` became a list of `conditions`
  (`key`, `operator`, `value`) with `conditionMatch` (`all` or `any`). `VisibilityType` is now
  `ConditionOperator` and `HasVisibility` is `HasConditions`.
* On a field class, the static `label()` is `getTypeLabel()`, the protected `rules()` is `fieldRules()`,
  and `$keyPrefix` is gone. `label()` and `rules()` are now fluent setters.
* The submissions tab of a form filters by rules instead of a select filter per choice field. A field
  type offers its rules in `getFilterConstraints()`; see [Filtering submissions](docs/usage.md#filtering-submissions).

* A notification lists its recipients in `to`, an address or `field:key` for the answer of a field,
  where v2 had `receivers`. It also has an `id`, `enabled`, `cc`, `bcc`, `reply_to`, `conditions` and
  `attach_files`.
  v3 no longer reads `receivers`; the conversion under
  [Stored forms and submissions](#stored-forms-and-submissions) rewrites the stored notifications.

* The thank-you message takes merge tags, and `getNotificationMessage()` returns it with them filled in.
  A new form shows a thank-you message by default instead of redirecting, with a text to start from.
* A form type's `settings()` show under the canvas instead of on a tab of their own, and the e-mail
  notifications share a Notifications tab with what happens after a submission.

* What a form does after a submission is one list, `forms.submit_notifications`, like the notifications,
  so a form can have a different outcome per answer. The upgrade migration turns `submit_notification_type`,
  `submit_notification_content`, `submit_notification_url` and `submit_notification_query` into its one
  item (`type`, `content`, `url`, `query`) and drops those columns; rolling it back puts them back.
  Read the outcomes with `$form->getSubmitNotifications()`. A custom redirect field
  (`redirectSchemaUsing()`) now binds to `url` instead of `submit_notification_url`, and the
  `RedirectUrl` cast is gone: a structured URL is stored in the list as it is.

### Stored forms and submissions

The package itself does not convert the canvas data of v2 forms; the Boost skill's migration above does,
or by hand, for each item in `forms.custom['fields']`:

* Replace `fieldType` (a class) with `type`, a name from the `fields` config. An `InputField` becomes
  `text`, `email`, `phone` or `number` after its `inputType` (`tel` is `phone`); a `SelectField` becomes
  `checkbox_list` when `multiple` is set and `radio` otherwise.
* Turn `visibleWhenKey`, `visibleWhenType` and `visibleWhenValue` into
  `conditions: [{key, operator, value}]`, without the `key_` prefix on `key`.
* Turn `column_span` into twelfths: on a 2-column form, 1 becomes 6 and 2 becomes 12. Replace
  `large: true` with 12.

Placeholders and receivers in e-mail notifications and the query string lose the prefix too:
`{{ $key_voornaam }}` becomes `{{ $voornaam }}`, which the editors then show as a merge tag. Submissions
stored with v2 keep their `key_` keys unless converted, so
their answers show under raw keys instead of labels. A v2 upload is a link inside `data` to a route that
no longer exists; v3 keeps uploads in the `files` column and links to them through signed URLs.

Submissions from before the upgrade have no snapshot of their form's fields, so an answer to a field
removed since then shows under its key, marked unknown.
