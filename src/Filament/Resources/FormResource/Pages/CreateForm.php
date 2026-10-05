<?php

namespace VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource;
use VanOns\FilamentFormBuilder\Models\Form;

class CreateForm extends CreateRecord
{
    protected static string $resource = FormResource::class;

    protected function getRedirectUrl(): string
    {
        /** @var Form $record */
        $record = $this->getRecord();

        return FormResource::recordUrl($record);
    }
}
