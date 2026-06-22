# Usage

## Submit notification

Each form has a "what happens after submission" section with two branches:

- **Content**: rich text shown after submit, stored in `submit_notification_content`.
- **URL**: a redirect target, stored in the dedicated `submit_notification_url`
  column. The column is cast with `VanOns\FilamentFormBuilder\Casts\RedirectUrl`,
  so it may hold either a plain URL string or a structured (JSON) value.

On submit, the controller resolves the URL via
`FilamentFormBuilderPlugin::resolveRedirectUrl($form->submit_notification_url, $form)`
and redirects to it.

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
string passthrough. The resolver also runs when a template returns its own
response (e.g. an Inertia redirect), so call `resolveRedirectUrl()` there too.

### Modifying submission data

A form template may override these hooks to transform submission data. They run
at different stages and affect different outputs — choose the right one:

| Hook                                                               | When it runs                    | Affects                                              |
|--------------------------------------------------------------------|---------------------------------|------------------------------------------------------|
| `modifyDataBeforeValidation(array $data, Form $form)`              | Before validation               | Validated input                                      |
| `modifyDataUsing(array $data, Form $form)`                         | Before the submission is stored | Stored `data`                                        |
| `modifyDataValues(array $data, FormSubmission $submission)`        | When rendering values           | **Both** the detail view **and** notification emails |
| `modifyResourceDataUsing(array $data, FormSubmission $submission)` | When rendering the detail view  | Detail view **only**                                 |

`modifyDataValues()` formats stored values into human-readable output while
keeping the original field-name keys, so it applies to both the Filament detail
view and the notification emails. This is the hook you want for value formatting
(e.g. mapping an enum value to its label):

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

