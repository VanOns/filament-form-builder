# Usage

## Form types

A form's type decides which fields it has in code, whether editors may add
their own on the canvas, and what happens around a submission. The `types`
config maps the name a form stores in its `template` column to the class behind
it:

```php
'types' => [
    'custom' => Forms\CustomForm::class,
    'contact' => Forms\ContactForm::class,
    'application' => App\Forms\VacancyApplication::class,
],
```

`custom` leaves every field to the editor, `contact` has all of them in code.
A new form is `custom`; editors pick another type behind the "Use a fixed form"
switch, which only shows when `custom` and at least one other type are
registered. A type of your own extends `FormType` and returns its fields from `fields()`,
built with the same field classes the canvas stores:

```php
use VanOns\FilamentFormBuilder\Enums\FieldWidth;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;
use VanOns\FilamentFormBuilder\Forms\CustomFields;
use VanOns\FilamentFormBuilder\Forms\FormType;

class VacancyApplication extends FormType
{
    public static function getLabel(): string
    {
        return 'Sollicitatie';
    }

    public function fields(): array
    {
        return [
            Fields\TextInputField::make('voornaam')->label('Voornaam')->required()->span(FieldWidth::HALF),
            Fields\TextInputField::make('achternaam')->label('Achternaam')->required()->span(FieldWidth::HALF),
            Fields\TextInputField::make('vacature')->hidden()->default(request()->query('vacature')),
            CustomFields::make(),
            Fields\CheckboxField::make('privacy')->label('Ik ga akkoord met de privacyverklaring')->required(),
            Fields\SubmitField::make('verstuur')->label('Verstuur'),
        ];
    }
}
```

- `CustomFields::make()` marks where the fields an editor builds go. A type
  without it has no canvas.
- The canvas shows the type's own fields around the editor's, locked, and marks
  the editor's part as "Custom fields". That part always starts and ends on a
  row of its own, on the page too. The type's keys stay reserved, so an editor's
  field never takes one.
- A hidden field renders as `<input type="hidden">` holding its default value,
  which suits context such as the vacancy a visitor applies for.
- A type without fields of its own and without `CustomFields::make()` shows no
  fields section at all; one with fields but no marker shows them read-only.

Every form renders through the `components/form` view. Pass another with
`<x-render-form :form="$form" view="forms.vacancy" />`.

### Hooks

Every method below runs on an instance, with the form in `$this->form`.

| Method                                                    | When it runs                          | Affects                                                                  |
|-----------------------------------------------------------|---------------------------------------|--------------------------------------------------------------------------|
| `beforeValidation(array $data)`                           | Before validation                     | What is validated, not what is stored                                    |
| `beforeStore(array $data)`                                | Before the submission is stored       | Stored `data`                                                            |
| `formatValues(array $values, FormSubmission $submission)` | Whenever answers are shown            | The table, the detail page, the export, the mails and their placeholders |
| `afterSubmission(FormSubmission $submission)`             | Once a visitor's submission is stored | Sends the notifications, queues the integrations                         |
| `response(FormSubmission $submission)`                    | After that                            | Replaces the redirect or message when it returns something               |

A visitor can only post the keys of the form's fields. A value `beforeStore()`
adds on its own is declared in `extraValues()` as key => label, so it gets a
label, a placeholder and a column like any field:

```php
public function extraValues(): array
{
    return ['received_from' => 'Received from'];
}

public function beforeStore(array $data): array
{
    return [...$data, 'received_from' => request()->headers->get('referer')];
}
```

`formatValues()` receives the answers after the fields formatted them (a choice
already reads as its label, a file as its download link) and keeps the field
keys. Whatever it returns is what every output shows:

```php
public function formatValues(array $values, FormSubmission $submission): array
{
    return [...$values, 'property_type' => PropertyType::tryFrom($values['property_type'] ?? '')?->getLabel()];
}
```

A type may also override `settings()` (extra fields for the form's
settings tab, stored in its `settings` column), `messages()` (validation
messages), and the admin toggles described below.

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

Besides text, e-mail, phone, number, choices and uploads there is a date,
stored as the date input sends it (`2026-10-07`) and shown in the format the
editor picks (`7 October 2026`, `07-10-2026`, `7 Oct 2026` or `2026-10-07`),
and a consent: a box that is always required and never ticked in advance,
beside a text whose links open the terms in a new tab. Only links, bold and
italic survive in that text. Its optional name ("Privacy") heads its column,
and without one the text itself does.

A field type that returns false from `isAvailable()` stays out of the palette,
while a field of that type already on a form stays there. reCAPTCHA does so
until it is enabled with a key and a secret (`RECAPTCHA_ENABLED`,
`RECAPTCHA_KEY`, `RECAPTCHA_SECRET`).

A field type that stores more than one value, such as a branch picker that
also keeps the branch's name and e-mail address, lists them all in
`getSubmissionColumns()` so each gets a label and a column. Only the keys in
`getInputKeys()`, the field's own key unless it says otherwise, are taken from
what the visitor posts; the other columns are for the field or its form type's
`beforeStore()` to fill in.

## Showing answers

The detail page of a submission shows one answer per field, in the order of the
form: as text by default, a link for an e-mail address or phone number, labels
for a list of choices, a check for a checkbox. A field that fills several
columns shows them together. Files have a card of their own.

A field can format its answer. The callback receives the value and the
submission, never an empty answer, and what it returns is what the table, the
detail page, the export and the mails show. An array becomes labels on the
detail page and a comma-separated list everywhere else:

```php
NumberField::make('hours')
    ->formatAnswerUsing(fn (int $value, FormSubmission $submission): string => "{$value} hours a week")
```

For something richer on the detail page only, give the field a Blade view of
its own. It receives `$value` (formatted), `$raw` (as stored), `$field`,
`$submission` and `$answer`, a `SubmissionAnswer`:

```php
BranchField::make('branch')->answerView('answers.branch')
```

A field type sets the default for all its fields in `$answerView`, which the
built-in views `filament-form-builder::answers.text`, `.email`, `.phone`,
`.boolean` and `.columns` use too.

Every submission keeps a snapshot of the form's fields as they were when it
was submitted. The answers to a field that has since been removed show apart,
under the label they had, and a choice still reads as the option's label. A
key the snapshot does not know shows under its own name. The export of a form
puts these answers together in an "Other data" column, one per line.

A submission counts as read once someone opens its page, stored in `read_at`
without touching `updated_at` or firing an update. Unread ones show an
envelope and bold text in the tables, the navigation and a form's Submissions
tab count them, and the tables filter and mark them in bulk. The page itself
can mark one unread again and steps to the newer and older submission of the
same form, also with the `k` and `j` keys.

## Filtering submissions

The submissions tab of a form filters by rules such as "Name contains jan" or
"Hours is at least 32", combined with and and or. Every field offers the rules
that suit its type: text matches whatever its case, a number compares as a
number, a choice goes by its options, a checkbox by whether it was ticked and
an upload by whether there is a file. The inverse of a rule, such as "does not
contain", also matches the submissions that left the field empty. The list of
all submissions only filters by form.

A field type of your own offers its rules in `getFilterConstraints()`. By
default every column it fills gets the text rules:

```php
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\AnswerConstraints;

public function getFilterConstraints(): array
{
    return [AnswerConstraints::choice($this->getKey(), $this->getLabel(), Branch::options())];
}
```

## After a submission

Once a visitor's submission is stored, the form type's `afterSubmission()`
sends the e-mail notifications and queues a `RunFormIntegrationsJob` for the
form's integrations. A submission created in code, by a seeder or an import,
triggers neither.

### E-mail notifications

The e-mail tab shows a form's notifications as cards: who they go to, when, and
how often they went out. A notification is edited in a slide-over:

- **To**: fields of the form that hold an e-mail address, or addresses typed in.
  Every recipient gets a mail of their own.
- **CC** and **BCC**: the same kind of recipients, getting a copy of each of
  those mails; an address the mail is already for gets no copy of it.
- **Reply to**: a field or an address, so a reply goes to the person who sent
  the form.
- **Sending**: always, or only when the answers meet conditions, the same rules
  fields use to show.
- **Attachments**: the uploads go along up to `uploads.attach_max_size`
  kilobytes together; larger ones stay a link.
- A switch on the card turns a notification off without deleting it, and
  **Test mail to me** sends it, filled in with the latest submission, to the
  person editing it.

On a form that already exists, saving, switching, duplicating or deleting a
notification stores it straight away; a form being created keeps them until it
is saved.

A new notification starts empty, as a confirmation to the person who sent the
form, or as a message for staff with every answer and a link to the
submission. A field type of your own offers its e-mail columns as recipients
in `getEmailColumns()`.

### Merge tags

The subject, the content and the sender name of an e-mail notification take
merge tags. The editor's tag button opens a picker that groups them and
searches as you type, and typing `{{` in the text offers them too: every answer
of the form by its label, the form title, all fields as one block, the
submission's number and date, and a link to the submission in the panel. They are stored as
Filament's merge tag nodes and filled in when the mail is sent, each answer
escaped. A tag for a field the form no longer has stays empty, and the e-mail
tab warns about it.

Content written before merge tags holds `{{ $key }}` as text. It is still read
as the same tag, and the editor shows it as one once the form is opened.

## Conditional fields

A custom field can depend on other answers: on its Conditions tab an editor adds
rules such as "Onderwerp is equal to anders" and chooses whether all of them or
any of them must hold. The field renders the rules as JSON in a `data-conditions`
attribute, which `resources/js/form-builder.js` evaluates to show or hide it. On
the server, a required conditional field is only required while its conditions
show it.

What a rule can test depends on the field it looks at, through the field type's
`getConditionOperators()`: any answer can equal a value or be empty, a number
can also be greater than, at least, less than or at most a value, a date can
be before, on or before, after or on or after a date, and a checkbox is ticked
or not. `getConditionPhrase()` gives the words a rule reads as. The same rules
decide when an e-mail notification goes out and which outcome follows a
submission.

## Submit notification

Each form has an "After submitting" section. Its outcomes are stored as one
list in `submit_notifications`, like the e-mail notifications: each has an
`id`, `conditions` and `conditionMatch`, a `type` (`content` or `url`), and its
`content`, `url` and `query`.

- **Content**: a thank-you message shown after submit. It takes the same merge
  tags as an e-mail notification, except the link to the submission in the
  panel, and they are filled in from the submission on submit.
- **URL**: a redirect target, a plain URL or a structured value a custom
  redirect field stores (see below), with an optional query string.

The last outcome has no conditions and is edited in place: what a form does by
default. "A different outcome per answer" adds outcomes before it as cards,
each under conditions on the answers, edited in a slide-over and ordered with
"Move up" and "Move down". They are saved with the form.

On submit, the controller resolves the outcome through the form type
(`$form->getType()->resolveSubmitNotification($submission)`): the first one
whose conditions the answers meet, skipping a kind the type does not allow
(`hasRedirect()`, `hasNotificationMessage()`). A URL goes through
`FilamentFormBuilderPlugin::resolveRedirectUrl($outcome['url'], $form)`, a
message gets its tags filled in. When a URL is present it redirects, otherwise
the message is flashed back. `getSubmitNotification($submission)` returns the
outcome that applies, for a type that handles the response itself.

### Passing field data via the query string

The URL branch has an optional query string, stored in the outcome's `query`.
Set the `redirect_query` config
flag to `false` to hide the field, or override `hasSubmitNotificationQuery()` on
a form type to drop it for that type alone. Either way a stored query string
is no longer appended on submit.

Few forms need them, so until a form has parameters a link brings up the rows.
An editor adds the parameters one row at a time, a name and a value of text
and merge tags, and they are stored as text: fixed text URL-encoded, tags as
the placeholders the e-mail notification understands:

```
vestiging={{ $vestiging }}&bron=website&form={{ $form_title }}
```

A query string stored with a part that is no parameter, such as a name without
`=`, stays a text field to edit by hand. Below the rows, the editor sees the
URL the latest submission would have sent its visitor to.

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

// Replace the URL-branch field schema. Bind your field(s) to `url`, the
// outcome's key. Returns an array of components.
FilamentFormBuilderPlugin::redirectSchemaUsing(fn () => [
    MyPagePicker::make('url')->required()->columnSpanFull(),
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
string passthrough. Neither runs when a form type returns its own `response()`
(e.g. an Inertia redirect), so call `$this->resolveSubmitNotification($submission)`
there to get the resolved values.

### Modifying the redirect URL and message per submission

A form type may override `modifySubmitNotification()` to change the redirect
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
  type may set it on a **Content** form too — a non-empty URL always wins.
- `$notificationMessage` is flashed as `submit_notification_content`; setting it
  also flashes `submit_notification_type` as `content`, so the message renders
  even on a URL form without a redirect.
- The form model caches its type instance, so `$this->form` and both
  properties are available in any instance method.

### Hard-coding the redirect URL or message

A form type may also drop either branch from the admin, so editors can't
configure it. Both default to `true`:

```php
public function hasRedirect(): bool
{
    return false;
}

public function hasNotificationMessage(): bool
{
    return true;
}
```

A third toggle hides only the query string, leaving the URL itself editable. It
defaults to the `redirect_query` config flag:

```php
public function hasSubmitNotificationQuery(): bool
{
    return false;
}
```

- The type toggle only shows the allowed branches, and hides itself when only
  one is left — that branch is then always used.
- When a form type allows neither, the whole submit notification section is
  hidden and the stored values are left untouched.
- A disabled branch is not read from the form on submit, so its property starts
  as `null`. Set it in `modifySubmitNotification()`:

```php
public function hasRedirect(): bool
{
    return false;
}

public function modifySubmitNotification(FormSubmission $submission): void
{
    $this->redirectUrl = route('thanks', ['submission' => $submission]);
}
```

### File uploads

Uploaded files are stored on the `uploads.disk` (default `local`) and kept in
the submission's `files` column, apart from the answers. They are served through
signed links that stay valid for `uploads.link_days` days (default 7), so a
notification mail can link to them for someone without an account. Add middleware
to `uploads.middleware` to put the links behind a login as well.


## Field widths

Every form lays its fields out on a grid of 12 columns. A field takes one of six
widths: a quarter, a third, a half, two thirds, three quarters or the full row.
Rows fill up on their own, so three thirds share one row and two halves the
next, and no more than four fields ever sit side by side.

The `layout` config value decides how many of those widths a project offers:

| `layout`      | Widths                     |
|---------------|----------------------------|
| `flexible`    | All six (the default)      |
| `two_columns` | Halves and the full row    |
| `full_width`  | The full row only          |

It applies everywhere: the builder only offers those widths, and stored widths,
including those set in code, round to the nearest one when a form is shown, so a
third reads as a half in `two_columns`.

In the builder, the width on a field's toolbar opens a picker with all six. A
new field takes the room left on the row it lands on, so a field dropped next to
two thirds becomes a third. Dropped against a field on a full row, it shares that
row: next to one field both become halves, next to two all three become thirds.
Dropped between two rows, or near the top or bottom edge of one, it gets a row of
its own. Moving a field works the same way, and the row it leaves closes up. The
canvas shows where the field lands and how wide everything becomes while it is
dragged. In code, pass the width
to `span()`:

```php
use VanOns\FilamentFormBuilder\Enums\FieldWidth;

TextInputField::make('voornaam')->span(FieldWidth::HALF);
```

Rows stay full. When a field on a full row changes width, the others follow at
the widths closest to their own: making the first of three thirds a half turns
the other two into quarters. Fields at the end that no longer fit move to a row
of their own right below, which they fill, so the rows below stay as they were.
A field that is deleted or moved away leaves its room to the others on its row,
and a copy shares its original's row or gets one of its own below.

Each field type has a minimum width, below which the builder offers nothing: a
third for titles, text blocks, text areas, uploads and checkboxes, half for
reCAPTCHA, a quarter for the rest. The submit button always takes the full row,
so it sits on a row of its own. A type of your own sets its own:

```php
public static function minWidth(): FieldWidth
{
    return FieldWidth::HALF;
}
```

A field is stored with its span in columns (`column_span`, 3 to 12). A value
that is no width of its own reads as the widest width that fits it, one below
the minimum as the minimum, and an empty one as the full row.
`$field->getWidth()` returns the `FieldWidth`, `$field->getColumnSpan()` the
number of columns.

### Laying the grid out in CSS

The shipped views render `{{ $form->getWrapperAttributes() }}` on the form and
`{{ $field->getWrapperAttributes() }}` on every field wrapper, which carries the
span. Two rules turn that into the grid, and a third stacks the fields on a
narrow screen:

```css
[data-form-builder-form] {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
}

[data-form-builder-input-wrapper] {
    grid-column: var(--form-builder-column-start, auto) / span var(--form-builder-column-span, 12);
}

@media (max-width: 40rem) {
    [data-form-builder-input-wrapper] {
        grid-column: 1 / -1;
    }
}
```

The fields an editor builds form a block of their own: the first of them starts
a new row, and so does the first field from code after them. Their wrapper
carries `--form-builder-column-start: 1` and `data-form-builder-new-row`, which
the rule above picks up. The span is also available as
`data-form-builder-column-span` for projects that would rather select on
attributes than custom properties. The package ships no
CSS of its own, so nothing changes visually until you add rules like these.
