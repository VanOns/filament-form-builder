# Usage

## Field types

The `fields` config maps the name a form stores to the class behind it:

```php
'fields' => [
    'text' => Fields\TextInputField::class,
    'email' => Fields\EmailField::class,
    // ...
    'postcode' => App\Forms\PostcodeField::class,
],
```

Stored forms only know the name, so a field class can be renamed, moved or
swapped for a project's own subclass by changing its entry here. Add an entry
to offer a field type of your own in the builder's palette.

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

### Passing field data via the query string

The URL branch has an optional query string, stored in
`submit_notification_query`. Set the `submit_notification_query_enabled` config
flag to `false` to hide the field, or override `hasSubmitNotificationQuery()` on
a template to drop it for that template alone. Either way a stored query string
is no longer appended on submit. An editor writes the parameters with the same
placeholders the e-mail notification uses:

```
vestiging={{ $vestiging }}&form={{ $form_title }}
```

`SubmissionPlaceholders::appendQuery()` fills them in from the submission,
URL-encodes the values and appends the result to the resolved redirect URL,
keeping any query string or fragment that URL already carries. A placeholder
without a value leaves its parameter empty. That same class backs the e-mail
notification, so both understand exactly the same tags.

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

A third toggle hides only the query string, leaving the URL itself editable. It
defaults to the `submit_notification_query_enabled` config flag:

```php
public static function hasSubmitNotificationQuery(): bool
{
    return false;
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

| Hook                                                               | When it runs                    | Affects                                                                  |
|--------------------------------------------------------------------|---------------------------------|--------------------------------------------------------------------------|
| `modifyDataBeforeValidation(array $data, Form $form)`              | Before validation               | Validated input                                                          |
| `modifyDataUsing(array $data, Form $form)`                         | Before the submission is stored | Stored `data`                                                            |
| `modifyDataValues(array $data, FormSubmission $submission)`        | Whenever answers are shown      | The table, the detail page, the export, the mails and their placeholders |
| `modifyResourceDataUsing(array $data, FormSubmission $submission)` | When showing the detail page    | Detail page **only**                                                     |

`modifyDataValues()` is the hook for formatting stored values into human-readable
output while keeping the original field-name keys. It receives the answers after
the form's own fields formatted them (a choice already reads as its label, a file
as its download link). Whatever it returns is what every output shows, so this is
the hook you want for value formatting (e.g. mapping an enum value to its label):

```php
public static function modifyDataValues(array $data, FormSubmission $submission): array
{
    return [
        ...$data,
        'property_type' => static::getOptions('property_type')[$data['property_type']] ?? null,
    ];
}
```

> **Note:** `modifyResourceDataUsing()` receives the answers as text under their
> labels and is used by the detail page only. Do **not** put value formatting here
> if you also want it in the table, the export or the mails; use
> `modifyDataValues()` instead.

### File uploads

Uploaded files are stored on the `form-uploads-disk` (default `local`) and kept in
the submission's `files` column, apart from the answers. They are served through
signed links that stay valid for `form-uploads-link-days` days (default 7), so a
notification mail can link to them for someone without an account. Add middleware
to `form-uploads-middleware` to put the links behind a login as well.


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

### Laying the grid out in CSS

You do not have to call the getters yourself. The shipped views already render
`{{ $form->getWrapperAttributes() }}` on the form and
`{{ $field->getWrapperAttributes() }}` on every field wrapper, and those now
carry the placement — including in views a project published earlier. Two rules
are enough to turn that into a grid:

```css
[data-form-builder-form] {
    display: grid;
    grid-template-columns: repeat(var(--form-builder-columns, 1), minmax(0, 1fr));
}

[data-form-builder-input-wrapper] {
    grid-column: var(--form-builder-column-start, auto) / span var(--form-builder-column-span, 1);
}
```

The same numbers are also available as `data-form-builder-columns`,
`data-form-builder-column-span` and `data-form-builder-column-start` for
projects that would rather select on attributes than custom properties. The
package ships no CSS of its own, so nothing changes visually until you add
rules like these.
