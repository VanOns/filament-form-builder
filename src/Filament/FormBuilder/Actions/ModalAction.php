<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Actions;

use Filament\Actions\Action;

class ModalAction extends Action
{
    protected function setUp(): void
    {
        $this->icon('heroicon-o-list-bullet')
            ->color('primary')
            ->modalHeading(fn () => $this->getLabel())
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('filament-form-builder::general.close'));
    }
}
