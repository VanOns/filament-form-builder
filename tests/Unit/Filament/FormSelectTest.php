<?php

use Livewire\Livewire;
use Tests\Fixtures\PickFormPage;
use VanOns\FilamentFormBuilder\Filament\Forms\Components\FormSelect;
use VanOns\FilamentFormBuilder\Models\Form;

it('offers every form by title and keeps the one picked', function () {
    $newsletter = Form::create(['title' => 'Nieuwsbrief', 'template' => 'custom']);
    $contact = Form::create(['title' => 'Contact', 'template' => 'custom']);

    $page = Livewire::test(PickFormPage::class, ['picked' => $contact->id])
        ->assertSet('data.form_id', $contact->id);

    $select = collect($page->instance()->form->getFlatComponents())
        ->first(fn ($component): bool => $component instanceof FormSelect);

    expect($select->getOptions())->toBe([$contact->id => 'Contact', $newsletter->id => 'Nieuwsbrief']);
});
