<?php

use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\ViewForm;
use VanOns\FilamentFormBuilder\Models\Form;

it('changes no notification from the read-only view page', function () {
    $form = Form::create([
        'title' => 'Read only',
        'template' => 'custom',
        'notifications' => [['id' => 'team', 'subject' => 'New', 'content' => '<p>Hi</p>', 'to' => ['hr@example.test']]],
    ]);

    $page = Livewire::test(ViewForm::class, ['record' => $form->getRouteKey()]);

    foreach (['toggle', 'delete'] as $action) {
        try {
            $page->callAction(TestAction::make($action)->schemaComponent('notifications', schema: 'form')->arguments(['item' => 'team']));
        } catch (Throwable) {
        }
    }

    expect($form->refresh()->notifications)->toHaveCount(1)
        ->and($form->notifications[0]['enabled'] ?? true)->toBeTrue();
});
