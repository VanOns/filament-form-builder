# Filament Form Builder

Filament Form Builder is a [FilamentPHP](https://filamentphp.com/) plugin that
let's you manage forms in your application:

- Send out email notification when a form has been submitted.
- Browse through the submitted forms.
- Control what is shown after a form has been submitted.

What is does not do (yet):

- Create and maintain forms.

## Compatibility

For certain Filament versions, changes have to be made that render the package backwards incompatible with the previous version.
Please see the table below to determine which version you need.

| Version                                                               | Filament |
|-----------------------------------------------------------------------|----------|
| v2 (current)                                                          | \>=4.0   |
| [v1](https://github.com/VanOns/filament-form-builder/tree/release/v1) | <4.0     |


## Installation

To get started with this package, add it to the repositories of your
`composer.json` file:

```json
"repositories": [
    {
        "type": "vcs",
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
            ->plugin(\VanOns\FilamentFormBuilder\FilamentFormBuilderPlugin::make())
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
['formId' => $form->id ])`. `$form` being an instance of
`VanOns\FilamentFormBuilder\Models\Form`.
- **may** have a form field `submitter_email`.
- **may** have a field `return_url` that will be used to redirect to, otherwise
you'll be redirect to the page the form is on.
- **may** use the flashed 'submit_notification_type' and
'submit_notification_content' to display content on the page redirected to
after submitting.

To get started, create a blade component for your form-template:
`php artisan make:component Forms/MyAwesomeForm`.

After creating the component, make sure to extend the `VanOns\FilamentFormBuilder\View\Components\FormComponent` class, instead of the default `Component` class.
When extending `FormComponent` you no longer need the `__construct` method in your class.

Make sure your form-template is constructed with an instance of
`VanOns\FilamentFormBuilder\Models\Form`, this will be used in the `action` of
the html form in your blade template:

```php
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\View\Components\FormComponent;

class MyAwesomeForm extends FormComponent
{
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

### Validation, attributes & messages

Similar to a request, you can use the `rules`, `attributes`, and `messages` methods in your form component.
These methods are used to validate the form data when it is submitted.
Behind the scenes, these methods are used in a `Request`.

```php
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\View\Components\FormComponent;

class MyAwesomeForm extends FormComponent
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }
    
    public function attributes(): array
    {
        return [
            'name' => 'Full name',
        ];
    }
    
    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your full name.',
        ];
    }
}
```

### Placeholders
The placeholder method is used to show the user what placeholders can be when creating form notifications.
You can choose to only add a key in this method, or add a key with a value. The value is used to set a fallback for when a field is not filled.
By default, the fallback is `-`.

```php
/**
 * @return array<string>
 */
public function placeholders(): array
{
    return [
        'name',
        'message' => 'No message',
    ];
}
```

Apart from the placeholders set in the form component, these placeholders are always available:
- `form_title`: The title of the form.
- `all_fields`: Adds all fields in the form, wrapped in a `panel` component, to the notification.

If for any reason you want to change the default placeholders, you can overwrite the `$defaultPlaceholders` variable set in a form component:
```php
class MyAwesomeForm extends FormComponent
{
    public array $defaultPlaceholders = [
        'form_title',
    ];
}
```

## Submit notification

Each form has a "what happens after submission" section with two branches:

- **Content**: rich text shown after submit, stored in `submit_notification_content`.
- **URL**: a redirect target, stored in the dedicated `submit_notification_url`
  column. The column is cast with `VanOns\FilamentFormBuilder\Casts\RedirectUrl`,
  so it may hold either a plain URL string or a structured (JSON) value.

On submit, the controller resolves the URL via
`FilamentFormBuilderPlugin::resolveRedirectUrl($form->submit_notification_url, $form)`
and redirects to it.

### Customising the redirect field

By default the URL branch is a single URL `TextInput`. Register a custom schema
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

## Custom form builder
**The custom form builder can be enabled/disabled by `\VanOns\FilamentFormBuilder\View\Components\Forms\CustomForm::class => <label>` to the `templates` array in the `filament-form-builder.php` config file.**

Select the 'custom' template in filament if you want to build your own form.
Choose what inputs you want to use in the form, and fill in the fields.

### Fields
You can use the following fields:
- Input (text, number, email, phone)
- Select (multi)
- Checkbox
- Text area
- Submit (button)

You can also create your own fields:
1. Create a class that extends `VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField`.
2. Set the view property to the path of your field's blade template (create this if it doesn't exist).
3. Add fields to the `getFields` method.
4. Add properties to your class that correspond to the field names.
5. Add the class to the `filament-form-builder.php` config file.

**Tip: look in the exisiting fields for examples.**

### Dynamic visibility
You can set fields to be dynamically visible based on other field's values.
To do this, fill the fields under the 'Visibility' section.

**You must import the JavaScript for this to work. See [JavaScript](#javascript) for more info.**

## Recaptcha
Enter your recaptcha keys in your .env file:
```
RECAPTCHA_ENABLED=true
RECAPTCHA_KEY=
RECAPTCHA_SECRET=
```

Then navigate to the form you want to use recaptcha on, and set the `$recaptcha` property to `true`.
This adds the `recaptcha` rules to the form, if you dont do this, the captcha will not be validated.
```php
class MyAwesomeForm extends FormComponent
{
    public bool $recaptcha = true;
}
```

## Overwriting existing views
You can overwrite the views of the default fields by publishing the views:
`php artisan vendor:publish --tag=filament-form-builder-views`.

This will publish the views to `resources/views/vendor/filament-form-builder/components/fields`, where you can then modify them.

## Translations

This package comes with translation, they can be published with:
`php artisan vendor:publish --tag=filament-form-builder-translations`.

The default for most files should be ok, one file to note is the `fields.php`
file. You should register translations for each of your form's fields in there.
The email that is sent out will look for a translation based on the field's
`name` property.

## Email Notifications

**Email notifications are enabled by default for all form templates.**

When a form is submitted, email notifications configured in the admin panel will automatically be sent to the specified recipients. You can configure multiple email notifications per form, each with their own subject, content, sender, and receivers.

### Disabling Email Notifications

If you want to disable email notifications for a specific form template, add the `hasNotifications()` method and return `false`:

```php
use VanOns\FilamentFormBuilder\View\Components\FormComponent;

class MyAwesomeForm extends FormComponent
{
    public static function hasNotifications(): bool
    {
        return false;
    }
    
    // Your form code...
}
```

### Configuring Email Notifications

In the admin panel:

1. Navigate to a form
2. Scroll to the "Email Notifications" section
3. Click "Add" to create a new notification
4. Fill in:
   - **Subject**: The email subject line (supports placeholders)
   - **Content**: The email body (supports placeholders and rich text)
   - **Sender**: The email address the notification will be sent from
   - **Receivers**: One or more email addresses or form fields containing email addresses
5. Save the form

You can add multiple email notifications to send different messages to different recipients.

## Integrations

The integration system allows you to automatically send form submission data to external services when a form is submitted. Each integration can have its own configuration fields and will track success/failure responses.

### How Integrations Work

When a form is submitted:
1. The form submission is created and saved
2. All configured integrations for that form are triggered
3. Each integration's `handle()` method is called
4. Response data (success/failure) is saved to the form submission
5. You can view integration responses in the form submission detail page

### Creating an Integration

To create a custom integration:

1. Create a new class that extends `VanOns\FilamentFormBuilder\Classes\Integration`
2. Implement the `handle()` method with your integration logic
3. Optionally override the `label()` method for a custom display name
4. Optionally define a `schema()` method for configuration fields
5. Register the integration in your config file

#### Example: N8N Webhook Integration

Here's an example showing how to handle responses:

```php
<?php

namespace App\Forms\Integrations;

use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Http;
use VanOns\FilamentFormBuilder\Classes\Integration;

class N8nIntegration extends Integration
{
    public static function label(): string
    {
        return 'N8N Webhook';
    }

    public static function schema(): array
    {
        return [
            TextInput::make('webhook_url')
                ->label('Webhook URL')
                ->url()
                ->required()
                ->placeholder('https://your-n8n-instance.com/webhook/...'),
        ];
    }

    public function handle(): void
    {
        $webhookUrl = $this->integration['webhook_url'] ?? null;
        
        if (!$webhookUrl) {
            throw new \Exception('Webhook URL is required');
        }

        // Your integration logic here...
        $response = Http::post($webhookUrl, [
            'data' => $this->formSubmission->data,
        ]);

        // Set success and response data
        if ($response->successful()) {
            $this->setSuccess(true)
                ->setResponse($response->json() ?? ['message' => 'Success']);
        } else {
            $this->setSuccess(false)
                ->setResponse([
                    'code' => $response->status(),
                    'message' => $response->body(),
                ]);
        }
    }
}
```

#### Example: Mailchimp Integration

Here's another example for adding subscribers to Mailchimp:

```php
<?php

namespace App\Forms\Integrations;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Http;
use VanOns\FilamentFormBuilder\Classes\Integration;

class MailchimpIntegration extends Integration
{
    public static function label(): string
    {
        return 'Mailchimp';
    }

    public static function schema(): array
    {
        return [
            TextInput::make('api_key')
                ->label('API Key')
                ->required()
                ->password(),
            
            TextInput::make('list_id')
                ->label('Audience/List ID')
                ->required(),
            
            Select::make('email_field')
                ->label('Email Field')
                ->options(fn () => [
                    'submitter_email' => 'Submitter Email',
                    'email' => 'Email',
                    'contact_email' => 'Contact Email',
                ])
                ->required()
                ->helperText('Which form field contains the email address?'),
        ];
    }

    public function handle(): void
    {
        $apiKey = $this->integration['api_key'];
        $listId = $this->integration['list_id'];
        $emailField = $this->integration['email_field'] ?? 'submitter_email';
        
        // Get email from form submission
        $email = $emailField === 'submitter_email' 
            ? $this->formSubmission->submitter_email
            : $this->formSubmission->data[$emailField] ?? null;
        
        if (!$email) {
            throw new \Exception("Email field '{$emailField}' not found in submission");
        }

        // Extract datacenter from API key
        $datacenter = substr($apiKey, strpos($apiKey, '-') + 1);
        
        // Add subscriber
        $response = Http::withBasicAuth('user', $apiKey)
            ->post("https://{$datacenter}.api.mailchimp.com/3.0/lists/{$listId}/members", [
                'email_address' => $email,
                'status' => 'subscribed',
                'merge_fields' => [
                    'FNAME' => $this->formSubmission->data['first_name'] ?? '',
                    'LNAME' => $this->formSubmission->data['last_name'] ?? '',
                ],
            ]);

        if ($response->successful()) {
            $this->setSuccess(true)
                ->setResponse([
                    'subscriber_id' => $response->json()['id'] ?? null,
                    'email' => $email,
                ]);
        } else {
            $this->setSuccess(false)
                ->setResponse($response->json());
        }
    }
}
```

### Registering Integrations

After creating your integration class, register it in your `config/filament-form-builder.php`:

```php
return [
    // ...existing config...
    
    'integrations' => [
        \App\Forms\Integrations\N8nIntegration::class,
        \App\Forms\Integrations\MailchimpIntegration::class,
        // Add more integrations here
    ],
];
```

### Enabling/Disabling Integrations for a Form Template

**Integrations are enabled by default for all form templates.**

If you want to disable integrations for a specific form template, add the `hasIntegrations()` method and return `false`:

```php
use VanOns\FilamentFormBuilder\View\Components\FormComponent;

class MyAwesomeForm extends FormComponent
{
    public static function hasIntegrations(): bool
    {
        return false;
    }
    
    // Your form code...
}
```

### Configuring Integrations in the Admin Panel

Once registered:

1. Navigate to a form in the admin panel
2. If the form template has integrations enabled, you'll see an "Integrations" section
3. Click "Add" to add a new integration
4. Select the integration type from the dropdown
5. Fill in the configuration fields (webhook URL, API keys, etc.)
6. Save the form

### Viewing Integration Responses

When viewing a form submission:

1. Navigate to the form submission detail page
2. Scroll to the "Integrations" section
3. You'll see all integrations that were triggered, including:
   - Integration name and type
   - Status badge (Success/Failed/Unknown)
   - Complete response data from the integration

### Integration Methods

Your integration class has access to:

#### Properties
- `$this->formSubmission` - The `FormSubmission` model instance
- `$this->integration` - Array containing the integration configuration

#### Methods
- `handle()` - **Required**. Implement your integration logic here
- `setSuccess(bool $success)` - Mark the integration as successful or failed
- `setResponse(string|array $response)` - Store response data
- `static::label()` - Return the display name for this integration
- `static::schema()` - Return Filament form fields for configuration

#### Example Access to Form Data

```php
public function handle(): void
{
    // Access form submission data
    $name = $this->formSubmission->data['name'] ?? 'Unknown';
    $email = $this->formSubmission->submitter_email;
    $formTitle = $this->formSubmission->form->title;
    
    // Access integration config
    $apiKey = $this->integration['api_key'];
    $webhookUrl = $this->integration['webhook_url'];
    
    // Your integration logic...
}
```

### Error Handling

Integrations automatically handle exceptions. If an exception is thrown in your `handle()` method:
- The integration is marked as failed
- The exception message is stored as the response
- Other integrations continue to execute
- The form submission is still created successfully

You can also manually set errors:

```php
public function handle(): void
{
    $response = Http::post($url, $data);
    
    if ($response->failed()) {
        $this->setSuccess(false)
            ->setResponse([
                'error' => 'API request failed',
                'status_code' => $response->status(),
                'message' => $response->body(),
            ]);
        return;
    }
    
    // Success handling...
}
```

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


## JavaScript
To enable dynamic visibility in the custom form builder, you need to import the JavaScript file provided by the package.
```js
// app.js
import '../../vendor/van-ons/filament-form-builder/resources/js/form-builder.js';
```

## Export Form Submissions
Because the Export action required extra steps, it is not enabled by default.
You can enable the Export action in the `filament-form-builder.php` config file. For this to work, you need to follow the steps in the FilamentPHP documentation:
[Export Action](https://filamentphp.com/docs/4.x/actions/export)