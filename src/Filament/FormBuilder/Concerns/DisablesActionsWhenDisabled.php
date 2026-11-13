<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Concerns;

use Filament\Actions\Action;

trait DisablesActionsWhenDisabled
{
    // Hiding the buttons is not enough: on a read-only page Livewire still runs an action called by name.
    public function prepareAction(Action $action): Action
    {
        return parent::prepareAction($action)
            ->authorize(fn (): bool => !$this->isDisabled());
    }
}
