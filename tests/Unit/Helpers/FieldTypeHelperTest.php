<?php

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;
use VanOns\FilamentFormBuilder\Helpers\FieldTypeHelper;
use VanOns\FilamentFormBuilder\Models\Form;

class ProjectTextField extends TextInputField
{
}

it('resolves a stored type name to its configured class', function () {
    expect(FieldTypeHelper::resolve('text'))->toBe(TextInputField::class);
});

it('knows no type by its class name or by a name nobody configured', function () {
    expect(FieldTypeHelper::resolve(TextInputField::class))->toBeNull()
        ->and(FieldTypeHelper::resolve('postcode'))->toBeNull();
});

it('lets a project swap the class behind a type without touching stored forms', function () {
    $form = Form::create([
        'title' => 'Contact',
        'template' => 'custom',
        'custom' => ['fields' => [['type' => 'text', 'label' => 'Naam']]],
    ]);

    config(['filament-form-builder.fields.text' => ProjectTextField::class]);

    expect($form->getFields()[0])->toBeInstanceOf(ProjectTextField::class);
});
