<?php

namespace VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource\Pages;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource;
use VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class ViewFormSubmission extends ViewRecord
{
    protected static string $resource = FormSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make(__('filament-form-builder::general.general'))
                    ->schema([
                        TextEntry::make('form.title')
                            ->label(__('filament-form-builder::general.form_title'))
                            ->url(function (FormSubmission $model) {
                                return FormResource::getUrl('edit', ['record' => $model->form]);
                            }),
                        TextEntry::make('created_at')
                            ->label(__('filament-form-builder::general.created_at'))
                            ->dateTime(),
                        TextEntry::make('submitter_email')
                            ->label(__('filament-form-builder::general.submitter_email')),
                    ])->columns(3),
                Section::make(__('filament-form-builder::general.form_content'))
                    ->schema([
                        KeyValueEntry::make('formattedData')
                            ->keyLabel(__('filament-form-builder::general.form_key'))
                            ->valueLabel(__('filament-form-builder::general.form_value')),
                    ]),
            ]);
    }
}
