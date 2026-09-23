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

it('getWrapperAttributes carries the column count for CSS to lay out', function () {
    config(['filament-form-builder.columns' => 3]);

    $attributes = Form::create(['title' => 'Contact'])->getWrapperAttributes();

    expect($attributes)->toContain('data-form-builder-columns=3')
        ->and($attributes)->toContain('--form-builder-columns:3');
});

it('renders attributes without spaces, because they are written unquoted', function () {
    config(['filament-form-builder.columns' => 3]);

    $attributes = Form::create(['title' => 'Contact'])->getWrapperAttributes();

    // AttributeHelper emits `key=value`; a space inside a value would end the
    // attribute early and swallow the rest of the tag.
    foreach (explode(' ', $attributes) as $pair) {
        expect(substr_count($pair, '='))->toBeGreaterThan(0);
    }
});
