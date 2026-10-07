<?php

use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\CreateForm;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use VanOns\FilamentFormBuilder\Forms\CustomForm;
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
