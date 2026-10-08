<?php

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\CreateForm;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use VanOns\FilamentFormBuilder\FilamentFormBuilderPlugin;
use VanOns\FilamentFormBuilder\Forms\ContactForm;
use VanOns\FilamentFormBuilder\Forms\CustomForm;
use VanOns\FilamentFormBuilder\Forms\FormType;
use VanOns\FilamentFormBuilder\Models\Form;

beforeEach(function () {
    $this->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));
});

it('builds a new form on the canvas unless a fixed type is switched on', function () {
    Livewire::test(CreateForm::class)
        ->assertSchemaStateSet(['template' => 'custom', 'fixed_type' => false])
        ->assertFormFieldHidden('template')
        ->set('data.fixed_type', true)
        ->assertSchemaStateSet(['template' => 'contact'])
        ->assertFormFieldVisible('template')
        ->set('data.fixed_type', false)
        ->assertSchemaStateSet(['template' => 'custom'])
        ->fillForm(['title' => 'Contact', 'submit_notifications.default.content' => '<p>Bedankt!</p>'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Form::sole()->template)->toBe('custom');
});

it('starts a new form with a thank-you message', function () {
    Livewire::test(CreateForm::class)
        ->fillForm(['title' => 'Contact'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Form::sole()->getSubmitNotifications()[0])->toMatchArray(['type' => 'content', 'content' => '<p>Thank you! We have received your message.</p>']);
});

it('shows the type of a form that has a fixed one', function () {
    $form = Form::create(['title' => 'Contact', 'template' => 'contact', 'submit_notifications' => [['type' => 'content', 'content' => '<p>Bedankt!</p>']]]);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertSchemaStateSet(['fixed_type' => true, 'template' => 'contact'])
        ->assertFormFieldVisible('template');
});

it('leaves the type out where the canvas is the only one', function () {
    config(['filament-form-builder.types' => ['custom' => CustomForm::class]]);

    Livewire::test(CreateForm::class)
        ->assertFormFieldDoesNotExist('fixed_type')
        ->assertFormFieldHidden('template')
        ->assertSchemaStateSet(['template' => 'custom']);
});

it('gives every form the settings a project adds on the plugin, or those of one type only', function () {
    FilamentFormBuilderPlugin::get()->formSettings(fn (?FormType $type): array => [
        Toggle::make('show_in_footer')->label('Show in the footer'),
        ...$type instanceof ContactForm ? [TextInput::make('department')->label('Department')] : [],
    ]);
    $form = Form::create(['title' => 'Terugbellen', 'template' => 'custom', 'submit_notifications' => [['type' => 'content', 'content' => '<p>Bedankt!</p>']]]);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertSee('Show in the footer')
        ->assertDontSee('Department')
        ->fillForm(['settings.show_in_footer' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($form->fresh()->settings)->toBe(['show_in_footer' => true]);

    Livewire::test(EditForm::class, ['record' => Form::create(['title' => 'Contact', 'template' => 'contact', 'submit_notifications' => [['type' => 'content', 'content' => '<p>Bedankt!</p>']]])->getRouteKey()])
        ->assertSee(['Show in the footer', 'Department']);
});
