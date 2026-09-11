# Usage

## Submit notification

Each form has a "what happens after submission" section with two branches:

- **Content**: rich text shown after submit, stored in `submit_notification_content`.
- **URL**: a redirect target, stored in the dedicated `submit_notification_url`
  column. The column is cast with `VanOns\FilamentFormBuilder\Casts\RedirectUrl`,
  so it may hold either a plain URL string or a structured (JSON) value.

On submit, the controller resolves both branches through the form template
(`$form->getFormComponent()->resolveSubmitNotification($submission)`): the URL via
`FilamentFormBuilderPlugin::resolveRedirectUrl($form->submit_notification_url, $form)`
and the message from `submit_notification_content`. When a URL is present it
redirects, otherwise the message is flashed back.

### Customizing the redirect field

By default, the URL branch is a single URL `TextInput`. Register a custom schema
(and a matching resolver) from a service provider's `boot()` to swap or extend
it, e.g. to let editors pick an internal page instead of typing a URL.

```php
use VanOns\FilamentFormBuilder\FilamentFormBuilderPlugin;

// Replace the URL-branch field schema. Bind your field(s) to
// `submit_notification_url`. Returns an array of components.
FilamentFormBuilderPlugin::redirectSchemaUsing(fn () => [
    MyPagePicker::make('submit_notification_url')->required()->columnSpanFull(),
]);

// Turn the stored value (string or array) into the final redirect URL.
FilamentFormBuilderPlugin::resolveRedirectUrlUsing(function (mixed $stored, Form $form): ?string {
    if (is_array($stored)) {
        return $stored['url'] ?? null; // resolve your structured value
    }

    return is_string($stored) ? $stored : null;
});
```

Both hooks are optional; the defaults keep the plain URL `TextInput` and
string passthrough. Neither runs when a template returns its own response
(e.g. an Inertia redirect), so call
`$submission->form->getFormComponent()->resolveSubmitNotification($submission)`
there to get the resolved values.

### Modifying the redirect URL and message per submission

A form template may override `modifySubmitNotification()` to change the redirect
URL or the notification message based on the submission. The `$redirectUrl` and
`$notificationMessage` properties hold the values configured on the form, so you
can read, extend or replace them:

```php
public function modifySubmitNotification(FormSubmission $submission): void
{
    if (($submission->data['property_type'] ?? null) === 'rental') {
        $this->redirectUrl = route('rental.thanks', ['submission' => $submission]);
    }

    // Or drop the redirect and show a message instead.
    if (empty($this->redirectUrl)) {
        $this->notificationMessage = __('Thanks :name!', ['name' => $submission->data['name']]);
    }
}
```

- `$redirectUrl` is only pre-filled when the form uses the **URL** branch, but a
  template may set it on a **Content** form too — a non-empty URL always wins.
- `$notificationMessage` is flashed as `submit_notification_content`; setting it
  also flashes `submit_notification_type` as `content`, so the message renders
  even on a URL form without a redirect.
- The form model caches the template instance, so `$this->form` and both
  properties are available in any instance method.

### Hard-coding the redirect URL or message

A template may also drop either branch from the admin, so editors can't
configure it. Both default to `true`:

```php
public static function hasRedirect(): bool
{
    return false;
}

public static function hasNotificationMessage(): bool
{
    return true;
}
```

- The type toggle only shows the allowed branches, and hides itself when only
  one is left — that branch is then always used.
- When a template allows neither, the whole submit notification section is
  hidden and the stored values are left untouched.
- A disabled branch is not read from the form on submit, so its property starts
  as `null`. Set it in `modifySubmitNotification()`:

```php
public static function hasRedirect(): bool
{
    return false;
}

public function modifySubmitNotification(FormSubmission $submission): void
{
    $this->redirectUrl = route('thanks', ['submission' => $submission]);
}
```

### Modifying submission data

A form template may override these hooks to transform submission data. They run
at different stages and affect different outputs — choose the right one:

| Hook                                                               | When it runs                    | Affects                                              |
|--------------------------------------------------------------------|---------------------------------|------------------------------------------------------|
| `modifyDataBeforeValidation(array $data, Form $form)`              | Before validation               | Validated input                                      |
| `modifyDataUsing(array $data, Form $form)`                         | Before the submission is stored | Stored `data`                                        |
| `modifyDataValues(array $data, FormSubmission $submission)`        | When rendering values           | **Both** the detail view **and** notification emails |
| `modifyResourceDataUsing(array $data, FormSubmission $submission)` | When rendering the detail view  | Detail view **only**                                 |

`modifyDataValues()` is the hook for formatting stored values into human-readable
output while keeping the original field-name keys. By default it returns the data
unchanged. Override it to apply your own formatting. When you do, the formatting
applies to both the Filament detail view and the notification emails. This is the
hook you want for value formatting (e.g. mapping an enum value to its label):

```php
public static function modifyDataValues(array $data, FormSubmission $submission): array
{
    return [
        ...$data,
        'property_type' => static::getOptions('property_type')[$data['property_type']] ?? null,
    ];
}
```

> **Note:** `modifyResourceDataUsing()` rewrites the keys into human labels and
> is used by the detail view only. Do **not** put value formatting here if you
> also want it reflected in emails — use `modifyDataValues()` instead. By default
> `modifyResourceDataUsing()` already runs your `modifyDataValues()` output through
> key formatting, so overriding it is rarely needed.


## Form columns

The number of grid columns a form is rendered with defaults to the `columns`
config value (`2`). A form template may override it:

```php
public static function columns(): int
{
    return 3;
}
```

Read it through the model with `$form->getColumns()` when rendering a form on
the front end.

### Field width

A large field spans the full row, whatever the column count. Each custom field
additionally carries a column span and an optional start column for anything in
between. The "width" and "start column" selects appear on forms with more than
2 columns — and hide again while "large" is checked, since large already means
full width. On forms with up to 2 columns only the large checkbox shows, so
existing projects keep working unchanged after an update. Set the
`field_column_settings` config flag to `true` to offer the selects everywhere.

When rendering, resolve a field's width with:

```php
$field->getColumnSpan($form->getColumns());  // int, 1..columns
$field->getColumnStart($form->getColumns()); // int or null (auto)
```

`getColumnSpan()` returns the full column count for large fields and the stored
span (default 1) otherwise, so both old and new data resolve through the same
call. Both getters cap their value at the column count, and the span is also
capped at the room left after the start column, so stored values never overflow
the grid when a template later reduces its columns.
