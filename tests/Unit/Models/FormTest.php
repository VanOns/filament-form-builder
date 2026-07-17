<?php

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

    $attributes = $form->getWrapperAttributes();

    expect($attributes)->toContain('enctype=multipart/form-data')
        ->and($attributes)->toContain("data-form-builder-form={$form->id}");
});
