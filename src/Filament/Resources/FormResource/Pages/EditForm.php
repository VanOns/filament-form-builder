<?php

namespace VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource;

class EditForm extends EditRecord
{
    protected static string $resource = FormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('save_top')
                ->label(__('filament-actions::edit.single.modal.actions.save.label'))
                ->action('save'),
            Actions\DeleteAction::make(),
        ];
    }
}
