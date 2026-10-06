<?php

use VanOns\FilamentFormBuilder\Forms\FormType;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

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

it('returns only the input fields when asked, whatever was asked first', function () {
    $form = Form::create([
        'title' => 'Bellen',
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'title', 'title' => 'Bel me'],
            ['type' => 'text', 'label' => 'Naam'],
        ]],
    ]);

    expect($form->getFields())->toHaveCount(2)
        ->and($form->getFields(inputsOnly: true))->toHaveCount(1)
        ->and($form->getFields())->toHaveCount(2);
});

it('has no fields when its type is not registered', function () {
    $form = Form::create(['title' => 'Leeg', 'template' => 'removed', 'custom' => ['fields' => [['type' => 'text', 'label' => 'Naam']]]]);

    expect($form->getType())->toBeInstanceOf(FormType::class)
        ->and($form->getFields())->toBe([]);
});
