# Upgrading

We aim to make upgrading between versions as smooth as possible, but sometimes it involves specific steps to be taken.
This document will outline those steps. And as much as we try to cover all cases, we might miss some. If you come
across such a case, please let us know by [opening an issue](https://github.com/VanOns/filament-form-builder/issues), or by
adding it yourself and creating a pull request.

## v2 to v3

v3 rebuilds how forms are defined and edited. It has no compatibility layer: templates, the field
classes and the stored canvas data all changed, and forms stored with v2 need converting by hand (see
[Stored forms and submissions](#stored-forms-and-submissions)).

### Installing

* Publish and run the migrations: `php artisan vendor:publish --tag=filament-form-builder-migrations`,
  then `php artisan migrate`. They add `form_submissions.files`, `form_submissions.field_snapshot`,
  `form_submissions.read_at` (existing submissions start out read) and
  `form_submission_notification_logs.notification_id`, and drop `form_submissions.submitter_email`.
  One also moves what a form does after a submission into a list, see below.
* `forms.template` now holds the name a form type is registered under instead of a template class: set
  it to `custom`, `contact` or the name of your own type.
* Republish the config, or compare yours with [`config/general.php`](config/general.php).
* Run `php artisan filament:assets`: the canvas brings its own stylesheet and Alpine component.
* Republish any views you published. `components/custom-form-renderer` is now `components/form`,
  `components/forms/contact-form` is gone and `components/fields/hidden-field` is new.
* Integrations run from `RunFormIntegrationsJob`, so they need a queue worker unless the queue is `sync`.

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
  A notification stored before is read as one of now and saved that way once its form is saved.

* The thank-you message takes merge tags, and `getNotificationMessage()` returns it with them filled in.
  A new form shows a thank-you message by default instead of redirecting.

* What a form does after a submission is one list, `forms.submit_notifications`, like the notifications,
  so a form can have a different outcome per answer. The migration turns `submit_notification_type`,
  `submit_notification_content`, `submit_notification_url` and `submit_notification_query` into its one
  item (`type`, `content`, `url`, `query`) and drops those columns; rolling it back puts them back.
  Read the outcomes with `$form->getSubmitNotifications()`. A custom redirect field
  (`redirectSchemaUsing()`) now binds to `url` instead of `submit_notification_url`, and the
  `RedirectUrl` cast is gone: a structured URL is stored in the list as it is.

### Stored forms and submissions

Nothing converts the canvas data of v2 forms. For each item in `forms.custom['fields']`:

* Replace `fieldType` (a class) with `type`, a name from the `fields` config. An `InputField` becomes
  `text`, `email`, `phone` or `number` after its `inputType` (`tel` is `phone`); a `SelectField` becomes
  `checkbox_list` when `multiple` is set and `radio` otherwise.
* Turn `visibleWhenKey`, `visibleWhenType` and `visibleWhenValue` into
  `conditions: [{key, operator, value}]`, without the `key_` prefix on `key`.
* Turn `column_span` into twelfths: on a 2-column form, 1 becomes 6 and 2 becomes 12. Replace
  `large: true` with 12.

Placeholders and receivers in e-mail notifications and the query string lose the prefix too:
`{{ $key_voornaam }}` becomes `{{ $voornaam }}`, which the editors then show as a merge tag. Submissions
stored with v2 keep their `key_` keys, so
their answers show under raw keys instead of labels. A v2 upload is a link inside `data` to a route that
no longer exists; v3 keeps uploads in the `files` column and links to them through signed URLs.

Submissions from before the upgrade have no snapshot of their form's fields, so an answer to a field
removed since then shows under its key, marked unknown.
