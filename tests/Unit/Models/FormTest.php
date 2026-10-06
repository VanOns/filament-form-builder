<?php

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TitleField;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\View\Components\Forms\CustomForm;

it('can create a form', function () {
    $form = Form::create(['title' => 'Contact']);

    expect($form->id)->toBeInt()
        ->and($form->title)->toBe('Contact');
});

it('has a submissions relationship', function () {
    $form = Form::create(['title' => 'Contact']);
    FormSubmission::create(['form_id' => $form->id, 'data' => []]);

    expect($form->submissions()->count())->toBe(1);
});

it('is soft deleted and not returned in default queries', function () {
    $form = Form::create(['title' => 'Contact']);
    $form->delete();

    expect(Form::find($form->id))->toBeNull()
        ->and(Form::withTrashed()->find($form->id))->not->toBeNull();
});

it('can be restored after soft deletion', function () {
    $form = Form::create(['title' => 'Contact']);
    $form->delete();
    $form->restore();

    expect(Form::find($form->id))->not->toBeNull();
});

it('getWrapperAttributes includes enctype and data-form-builder-form', function () {
    $form = Form::create(['title' => 'Contact']);

    $attributes = $form->getWrapperAttributes()->toHtml();

    expect($attributes)->toContain('enctype="multipart/form-data"')
        ->and($attributes)->toContain("data-form-builder-form=\"{$form->id}\"");
});

it('getWrapperAttributes carries the column count for CSS to lay out', function () {
    config(['filament-form-builder.columns' => 3]);

    $attributes = Form::create(['title' => 'Contact'])->getWrapperAttributes()->toHtml();

    expect($attributes)->toContain('data-form-builder-columns="3"')
        ->and($attributes)->toContain('--form-builder-columns:3');
});

it('returns only the input fields when asked, whatever was asked first', function () {
    $form = Form::create([
        'title' => 'Bellen',
        'template' => CustomForm::class,
        'custom' => ['fields' => [
            ['fieldType' => TitleField::class, 'title' => 'Bel me'],
            ['fieldType' => TextInputField::class, 'label' => 'Naam'],
        ]],
    ]);

    expect($form->getFields())->toHaveCount(2)
        ->and($form->getFields(inputsOnly: true))->toHaveCount(1)
        ->and($form->getFields())->toHaveCount(2);
});

it('is not custom without a template', function () {
    expect(Form::create(['title' => 'Leeg'])->isCustom())->toBeFalse();
});
