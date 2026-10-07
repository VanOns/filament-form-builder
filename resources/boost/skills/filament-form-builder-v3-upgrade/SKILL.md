---
name: filament-form-builder-v3-upgrade
description: Upgrade a Laravel project from van-ons/filament-form-builder v2 to v3, including converting the forms, notifications and submissions it stored. Use when someone asks to upgrade or migrate the form builder to v3, when v2 templates, `key_` field keys, `fieldType` canvas data or `receivers` in notifications have to become v3, or when forms break after `composer require van-ons/filament-form-builder:^3.0`.
---

# Upgrading filament-form-builder from v2 to v3

v3 has no compatibility layer: templates, field classes and the stored canvas data all changed. Work through
the steps in order and keep the developer informed. The full upgrade guide is
https://github.com/VanOns/filament-form-builder/blob/main/UPGRADING.md (it is not shipped in `vendor`); the
v3 source in `vendor/van-ons/filament-form-builder` is the reference for anything below.

## Stop and ask the developer when

- the project is below v2.9, or there is no recent database backup: the conversion rewrites stored forms and
  submissions and cannot be rolled back;
- the project has v2 field classes of its own (in the v2 `fields` config) without an obvious v3 field type;
- a v2 template does something in `render()`, its own view or `modifyResourceDataUsing()` that v3 has no
  direct place for;
- a notification sends to `submitter_email` and its form has no e-mail field (the conversion drops it);
- uploads were stored on a disk the app no longer has.

## 1. Before changing anything

1. `composer show van-ons/filament-form-builder`: on v2 below 2.9, require `^2.9`, publish and run its
   migrations first.
2. Note the v2 values the conversion needs, from `config/filament-form-builder.php`: `templates` (class =>
   label), `columns`, extra classes in `fields`, `form-uploads-disk`. Also note which template classes
   override `columns()`.

## 2. Install v3

```bash
composer require van-ons/filament-form-builder:^3.0 -W
```

v3 needs Filament 4.13 or 5.8 at least.

## 3. Config

Republish (`php artisan vendor:publish --tag=filament-form-builder-config --force`) and carry the project's
values over, or edit the published file against `vendor/van-ons/filament-form-builder/config/general.php`.
The renames: `templates` (class => label) becomes `types` (name => class); `fields` becomes name => class;
`field_visibility_settings` is `field_conditions`; `email_notification_enabled` is `email_notifications`;
`submit_notification_query_enabled` is `redirect_query`; `rate-limit-hour` is `rate_limit_per_hour`;
`form-middleware` is `form_middleware`; `form-uploads-disk`, `-max-size` and `-middleware` move into
`uploads` (`disk`, `max_size`, `middleware`); `add_nav_group` is `navigation_group`; `enable_export_action`
is `export_action`. `columns` and `field_column_settings` are gone.

Set `uploads.disk` to the v2 `form-uploads-disk` (v2 defaulted to `private`), so old uploads stay readable.

## 4. Templates become form types

Each v2 template class becomes a class extending `VanOns\FilamentFormBuilder\Forms\FormType`, registered
under a name in `types`. Model it on `vendor/van-ons/filament-form-builder/src/Forms/ContactForm.php` and
`CustomForm.php`:

- `rules()`, `attributes()` and `placeholders()` become `fields()`, built with the field classes
  (`TextInputField::make('name')->label('Name')->required()`); values `modifyDataUsing()` added go in
  `extraValues()` and are set in `beforeStore()`.
- `isCustom()` becomes `CustomFields::make()` inside `fields()`, where the editor's fields go.
- `hasRecaptcha()` becomes `RecaptchaField::make('g-recaptcha-response')` in `fields()`.
- The static hooks become instance methods with the form in `$this->form`: `modifyDataBeforeValidation` is
  `beforeValidation`, `modifyDataUsing` is `beforeStore`, `modifyDataValues` is `formatValues`,
  `afterSubmissionCreated` is `afterSubmission`, `successResponse` is `response`.
- `render()` and the template's view are gone: forms render through `<x-render-form>`, which takes a
  `view` of the project's own.

Built-in templates map to `custom` and `contact`. Search the project for the old class names and for
`getFormComponent()` (now `getType()`), `submitter_email`, `key_` and `data-visible-when` in views, scripts
and front-end code.

## 5. Convert the stored data

1. Publish the package's upgrade migration:
   `php artisan vendor:publish --tag=filament-form-builder-upgrade-migrations`.
2. Create a migration that runs after it, `php artisan make:migration convert_filament_form_builder_v2_data`,
   and replace its contents with `references/convert-v2-forms.php` from this skill.
3. Fill in its public properties: `$templates` (every v2 template class => its v3 type name), `$columns` (the
   v2 `columns`), `$templateColumns` (templates that overrode `columns()`), `$fieldTypes` (project field
   classes => v3 field type names). Leave `$convertSubmissions` on unless the developer says otherwise.
4. `php artisan migrate`. The conversion stops before changing anything when a template or field class is
   missing from the maps, and names them.

What it converts, per form still in the v2 shape:

- canvas fields: `fieldType` to `type` (an `InputField` by its `inputType`, a `SelectField` to `radio` or,
  with `multiple`, `checkbox_list`), the key without `key_`, `visibleWhen*` to `conditions`, `column_span`
  and `large` to twelfths of the row; `column_start` is dropped, titles, text blocks and buttons fill the row;
- `forms.template` from class to type name;
- notifications: `receivers` to `to` (`field:key` for a field), `submitter_email` to the form's first e-mail
  field, `{{ $key_x }}` to `{{ $x }}` in subject, content and sender name, and an id per notification;
- the thank-you message and redirect query string: `{{ $key_x }}` to `{{ $x }}`;
- submissions from before v3 (no `field_snapshot`): answers lose `key_`, and upload links in `data` move
  to `files` as `{path, name}`.

It does not convert template-based forms' own behaviour, project field classes' extra settings, or uploads
whose files are missing.

## 6. Views, assets and front end

- Republish any published views (`--tag=filament-form-builder-views`) and port the project's changes:
  `components/custom-form-renderer` is `components/form`, field wrappers carry `ffb-*` classes.
- `php artisan filament:assets`, and remove the project's own script tag for `form-builder.js`.
- A front end of its own (Inertia, React, Vue) reads conditions with `resources/js/conditions.js` and
  `$form->getFieldConditions()` instead of `data-visible-when-*` attributes.
- That front end also has to send the honeypot: pass `$form->getHoneypot()` to the page and render its two
  fields with `resources/js/honeypot.js` (see "In a front end of your own" in the docs). Without them the
  server answers 422 with an error on `ffb_token`. A project view for `<x-render-form>` adds
  `<x-filament-form-builder::honeypot :form="$form" />`. If the developer would rather not, `hasHoneypot()`
  returning false turns it off for a form type, and `honeypot.enabled` for all forms.
- Integrations run from `RunFormIntegrationsJob`: the app needs a queue worker unless the queue is `sync`.

## 7. Check

- Open every form in the panel: fields, widths and conditions on the canvas, notifications as cards.
- Submit each form on the site, including one with an upload, and check the mails and the submission page.
- Open an old submission: answers under their labels, uploads downloadable.
- Run the project's tests.

Tell the developer what was converted and what still needs a person: forms whose fields were dropped,
notifications without recipients, and anything from the stop list above.
