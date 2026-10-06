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
  then `php artisan migrate`. They add `form_submissions.files`.
* `forms.template` now holds the name a form type is registered under instead of a template class: set
  it to `custom`, `contact` or the name of your own type.
* Republish the config, or compare yours with [`config/general.php`](config/general.php).
* Run `php artisan filament:assets`: the canvas brings its own stylesheet and Alpine component.
* Republish any views you published. `components/custom-form-renderer` is now `components/form`,
  `components/forms/contact-form` is gone and `components/fields/hidden-field` is new.
* Integrations run from `RunFormIntegrationsJob`, so they need a queue worker unless the queue is `sync`.

### Config

| v2                                           | v3                                                    |
|----------------------------------------------|-------------------------------------------------------|
| `templates`: class => label                  | `types`: name => class                                |
| `fields`: a list of classes                  | `fields`: name => class                               |
| `field_visibility_settings`                  | `field_conditions`                                    |
| `field_column_settings`                      | Removed, every field has a column span                |
| `email_notification_enabled`: `false`        | `true`                                                |
| `form-uploads-disk`: `private`               | `local`                                               |
| `form-uploads-middleware`: `['web', 'auth']` | `[]`, the download links are signed                   |
|                                              | `form-uploads-link-days`: how long a link stays valid |

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

| v2 (static)                                               | v3                                                 |
|-----------------------------------------------------------|----------------------------------------------------|
| `modifyDataBeforeValidation(array $data, Form $form)`     | `beforeValidation(array $data)`                    |
| `modifyDataUsing(array $data, Form $form)`                | `beforeStore(array $data)`                         |
| `modifyDataValues(array $data, FormSubmission $s)`        | `formatValues(array $values, FormSubmission $s)`   |
| `modifyResourceDataUsing(array $data, FormSubmission $s)` | `formatDetails(array $details, FormSubmission $s)` |
| `afterSubmissionCreated(FormSubmission $s)`               | `afterSubmission(FormSubmission $s)`               |
| `successResponse(FormSubmission $s)`                      | `response(FormSubmission $s)`                      |

`columns()`, `settings()`, `messages()`, `hasRedirect()`, `hasNotificationMessage()`,
`hasSubmitNotificationQuery()`, `hasNotifications()` and `hasIntegrations()` keep their names.

* On the model, `getFormComponent()` is `getType()`; `isCustom()`, `getCustomFormRules()`,
  `getCustomFormLabels()` and `getFormAttributes()` give way to `getRules()` and `getSubmissionFields()`.
* Notifications and integrations only run for a visitor's submission, no longer for every created
  `FormSubmission`.
* A posted `submitter_email` is no longer read: the submitter is the first e-mail field filled in.

### Fields

* `InputField` is split into `TextInputField`, `EmailField`, `PhoneField` and `NumberField`, and
  `SelectField` into `RadioField`, `CheckboxListField` and `DropdownField`.
* Keys no longer get a `key_` prefix: a field labelled "Voornaam" posts and stores `voornaam`.
* `large` and `column_start` are gone; a field only has `column_span`.
* `visibleWhenKey`, `visibleWhenType` and `visibleWhenValue` became a list of `conditions`
  (`key`, `operator`, `value`) with `conditionMatch` (`all` or `any`). `VisibilityType` is now
  `ConditionOperator` and `HasVisibility` is `HasConditions`.
* On a field class, the static `label()` is `getTypeLabel()`, the protected `rules()` is `fieldRules()`,
  and `$keyPrefix` is gone. `label()` and `rules()` are now fluent setters.

### Stored forms and submissions

Nothing converts the canvas data of v2 forms. For each item in `forms.custom['fields']`:

* Replace `fieldType` (a class) with `type`, a name from the `fields` config. An `InputField` becomes
  `text`, `email`, `phone` or `number` after its `inputType` (`tel` is `phone`); a `SelectField` becomes
  `checkbox_list` when `multiple` is set and `radio` otherwise.
* Turn `visibleWhenKey`, `visibleWhenType` and `visibleWhenValue` into
  `conditions: [{key, operator, value}]`, without the `key_` prefix on `key`.
* Replace `large: true` with a `column_span` of the form's column count.

Placeholders and receivers in e-mail notifications and the query string lose the prefix too:
`{{ $key_voornaam }}` becomes `{{ $voornaam }}`. Submissions stored with v2 keep their `key_` keys, so
their answers show under raw keys instead of labels. A v2 upload is a link inside `data` to a route that
no longer exists; v3 keeps uploads in the `files` column and links to them through signed URLs.
