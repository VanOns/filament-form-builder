<?php

namespace VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource;

class ViewForm extends ViewRecord
{
    protected static string $resource = FormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
