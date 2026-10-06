<?php

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\CheckboxListField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\DropdownField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\EmailField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FileUploadField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextAreaField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TitleField;

it('builds the same field in code as the canvas stores', function () {
    $inCode = EmailField::make('email')
        ->label('E-mailadres')
        ->description('Voor de bevestiging')
        ->placeholder('naam@domein.nl')
        ->required()
        ->span(1);

    $fromCanvas = new EmailField([
        'key' => 'email',
        'label' => 'E-mailadres',
        'description' => 'Voor de bevestiging',
        'placeholder' => 'naam@domein.nl',
        'required' => true,
        'column_span' => 1,
    ]);

    expect(get_object_vars($inCode))->toEqual(get_object_vars($fromCanvas))
        ->and($inCode->getRules())->toBe($fromCanvas->getRules());
});

it('adds the rules given in code after the field\'s own', function () {
    expect(TextAreaField::make('message')->required()->rules(['max:6000'])->getRules())
        ->toBe(['message' => ['required', 'max:6000']])
        ->and(TextInputField::make('name')->getRules())
        ->toBe(['name' => ['nullable']]);
});

it('adds the rules given in code to a choice and an upload too', function () {
    $choice = CheckboxListField::make('interests')->options(['web' => 'Websites'])->rules(['max:2'])->getRules();
    $upload = FileUploadField::make('cv')->multiple()->rules(['max:3'])->getRules();

    expect($choice['interests'])->toBe(['nullable', 'array', 'max:2'])
        ->and($upload['cv'])->toBe(['nullable', 'array', 'max:3'])
        ->and($upload)->toHaveKey('cv.*');
});

it('takes options as value and label pairs', function () {
    $dropdown = DropdownField::make('country')->options(['nl' => 'Nederland', 'be' => 'België']);

    expect($dropdown->options)->toBe([
        ['value' => 'nl', 'label' => 'Nederland'],
        ['value' => 'be', 'label' => 'België'],
    ])->and($dropdown->getFilterOptions())->toBe(['nl' => 'Nederland', 'be' => 'België']);
});

it('sets the hidden, default and conditional bits in code too', function () {
    $field = TextInputField::make('vacancy_title')
        ->hidden()
        ->default('Adviseur')
        ->conditions([['key' => 'type', 'operator' => 'equals', 'value' => 'sollicitatie']], match: 'any');

    expect($field->isHidden())->toBeTrue()
        ->and($field->getDefaultValue())->toBe('Adviseur')
        ->and($field->getConditions()->toArray()['match'])->toBe('any');
});

it('builds a title in code', function () {
    $title = TitleField::make('intro')->title('Contactgegevens')->headingLevel('h3');

    expect($title->title)->toBe('Contactgegevens')
        ->and($title->headingLevel)->toBe('h3');
});
