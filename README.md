# Filament Form Builder

Filament Form Builder is a [FilamentPHP](https://filamentphp.com/) plugin that
let's you manage forms in your application:

- Send out email notification when a form has been submitted.
- Browse through the submitted forms.
- Control what is shown after a form has been submitted.

What is does not do (yet):

- Create and maintain forms.

## Installation

To get started with this package, add it to the repositories of your
`composer.json` file:

```json
"repositories": [
    {
        "type": "path",
        "url": "https://github.com/VanOns/filament-form-builder"
    }
],
```

- Install the package: `composer require van-ons/filament-form-builder`.
- Publish the migrations:
`php artisan vendor:publish --tag=filament-form-builder-migrations`.
- Run the migrations: `php artisan migrate`.
- Add the plugin to your Filament Panel Provider:

```php
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ...
            ->plugin(VanOns\FilamentFormBuilder\FilamentFormBuilderPlugin::make())
            ...
    }
}
```

You will now see two new resources in your admin panel.

## Usage

When creating a form, you'll be asked to select a template. This is the form
that can be filled in by the end-user. For each submitted form, a
`VanOns\FilamentFormBuilder\Models\FormSubmission` will be created.

To be able to set up and use a form, we must create at least 1 template to use.

### Creating a template

You're free to set up form-templates as you want to, but there are a few requirements.
Your form-template:

- **must** `POST` to a specific route: `route('filament-form-builder.form.store',
['formId' => $form->id ])`. `$form` being the
- **may** have a form field `submitter_email`.
- **may** have a field `return_url` that will be used to redirect to, otherwise
you'll be redirect to the page the form is on.
- **may** use the flashed 'submit_notification_type' and
'submit_notification_content' to display content on the page redirected to
after submitting.

To get started, create a blade component for your form-template:
`php artisan create:component Forms/MyAwesomeForm`.

Make sure your form-template is constructed with an instance of
`VanOns\FilamentFormBuilder\Models\Form`, this will be used in the `action` of
the html form in your blade template:

```php
use VanOns\FilamentFormBuilder\Models\Form;

class MyAwesomeForm extends Component
{
    public function __construct(public Form $form) {}

    public function render(): View|Closure|string
    {
        return view('components.forms.my-awesome-form');
    }
}
```

In your `resources/views/components/forms/my-awesome-form.blade.php` implement
the form with the required items:

- The `action` pointing to the route `filament-form-builder.form.store`.
- The `method` being `POST`.
- A hidden field containing the url you want to be redirected to. (optional)
- A `submitter_email` field.

Example:

```blade
<div>
    <form method="POST" action={{ route('filament-form-builder.form.store', ['formId' => $form->id ]) }}>
        @csrf
        <input type="hidden" name="return_url" value="{{route('index')}}">
        <input type="text" name="submitter_email" value="">
        <input type="text" name="field1" value="">
        <input type="text" name="field2" value="">
        <button type="submit">send</button>
    </form>
</div>
```

### Registering a template

To start registering templates, you must publish the config:
`php artisan vendor:publish --tag=filament-form-builder-config`.

You can register your component in the `templates` array.

The key must be the component's class, the value is the label that the admin
will see in the templates dropdown in the Filament Resource.

### Using a form

To use a form simply add the provided component in your blade view, and give it
an instance of `VanOns\FilamentFormBuilder\Models\Form`:

```blade
@php
    $myForm = VanOns\FilamentFormBuilder\Models\Form::first();
@endphp

<div>
    <x-render-form :form="$myForm" />
</div>
```

What this will do in the background is

- Take the form's template property, which is the class string of your
configured template.
- Call `Illuminate\Support\Facades\Blade::renderComponent()`, with a new
instance of the template class, passing it the form instance.
- Resulting in your template being rendered.

### Validation

By adding a `public static function rules()` to your form component, you
add validation rules to your form:

```php
use VanOns\FilamentFormBuilder\Models\Form;

class MyAwesomeForm extends Component
{
    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'name' => ['nullable', 'sometimes', 'string']
        ];
    }
}
```

## Translations

This package comes with translation, they can be published with:
`php artisan vendor:publish --tag=filament-form-builder-translations`.

The default for most files should be ok, one file to note is the `fields.php`
file. You should register translations for each of your form's fields in there.
The email that is sent out will look for a translation based on the field's
`name` property.

## Events

All models events can be hooked into:

FormSubmissions:

- `VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionCreated::class`
- `VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionUpdated::class`
- `VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionDeleted::class`
- `VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionRestored::class`
- `VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionForceDeleted::class`

Forms:

- `VanOns\FilamentFormBuilder\Events\Form\FormCreated::class`
- `VanOns\FilamentFormBuilder\Events\Form\FormUpdated::class`
- `VanOns\FilamentFormBuilder\Events\Form\FormDeleted::class`
- `VanOns\FilamentFormBuilder\Events\Form\FormRestored::class`
- `VanOns\FilamentFormBuilder\Events\Form\FormForceDeleted::class`

## Customizing the email notification

You can customize the email notification in two ways:

### Publish the views

You can publish and customize the email's markdown blade view by publishing it:
`php artisan vendor:publish --tag=filament-form-builder-views`.

### Turn of the notification and re-implement it

You can completely disable emails send by the package by disabling
`email_notification_enabled` in the config.

You're the free to set up a listener for the
`VanOns\FilamentFormBuilder\Models\Form\FormSubmissionCreated::class` event,
and re-implement the email.

## Example: Adding a form to your model

This package does not restrict you to using forms in specific way. If you have
a model, `Page::class` for example, you could relate a form to your model as
following:

in `App\Models\Page::class`:

```php

public function form(): BelongsTo
{
    return $this->belongsTo(VanOns\FilamentFormBuilder\Models::class);
}
```

Make sure you have a `form_id` field in your `Page::class`'s table:

```php
    Illuminate\Support\Facades\Schema::create('pages', function (Blueprint $table) {
        $table->foreignId('form_id')
            ->constrained('forms')
            ->nullOnDelete();
    });
```

Then in to select one in a FilamentPHP resource:

```php
Filament\Forms\Components\Select::make('form_id')
    ->relationship(name: 'form', title: 'title')
    ->searchable()
    ->preload();
```
