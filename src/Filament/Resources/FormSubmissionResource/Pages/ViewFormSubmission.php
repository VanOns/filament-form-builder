<?php

namespace VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource\Pages;

use Filament\Actions\Action;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use VanOns\FilamentFormBuilder\Classes\Integration;
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

    public function infolist(Schema $schema): Schema
    {
        /** @var FormSubmission $record */
        $record = $this->getRecord();
        $actions = array_map(function ($url) {
            return Action::make('view-url')
                ->color('gray')
                ->label($url)
                ->url($url)
                ->openUrlInNewTab();
        }, $record->getAllUrlsInData());

        return $schema
            ->schema([
                Section::make(__('filament-form-builder::general.general'))
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('form.title')
                            ->label(__('filament-form-builder::general.form_title'))
                            ->url(function (FormSubmission $record) {
                                return FormResource::getUrl('edit', ['record' => $record->form]);
                            }),
                        TextEntry::make('created_at')
                            ->label(__('filament-form-builder::general.created_at'))
                            ->dateTime(),
                        TextEntry::make('submitter_email')
                            ->label(__('filament-form-builder::general.submitter_email')),
                    ])->columns(3),
                Section::make(__('filament-form-builder::general.form_content'))
                    ->columnSpanFull()
                    ->schema([
                        KeyValueEntry::make('formattedKeyData')
                            ->hiddenLabel()
                            ->keyLabel(__('filament-form-builder::general.form_key'))
                            ->valueLabel(__('filament-form-builder::general.form_value')),
                    ]),

                Section::make(__('filament-form-builder::general.found_urls'))
                    ->hidden(empty($actions))
                    ->schema([
                        Actions::make($actions),
                    ]),

                $this->getIntegrationsSection($record),
            ]);
    }

    public function getIntegrationsSection(FormSubmission $record): Section
    {
        $integrationResponses = $record->integrations ?? [];

        if (empty($integrationResponses)) {
            return Section::make(__('filament-form-builder::general.integration_responses'))
                ->hidden(empty(Integration::getIntegrations()))
                ->icon('heroicon-o-server-stack')
                ->iconSize(IconSize::ExtraLarge)
                ->description(__('filament-form-builder::general.no_integrations'));
        }

        $entries = [];

        foreach ($integrationResponses as $index => $integrationData) {
            $integrationClass = $integrationData['integration'] ?? 'Unknown';

            $label = class_exists($integrationClass) && is_subclass_of($integrationClass, Integration::class)
                ? $integrationClass::label()
                : class_basename($integrationClass);

            $success = $integrationData['response']['success'] ?? null;

            $color = match ($success) {
                true => 'success',
                false => 'danger',
                default => 'gray',
            };

            $entries[] = Section::make($label)
                ->description($integrationClass)
                ->collapsed()
                ->icon(match ($success) {
                    true => 'heroicon-o-check-circle',
                    false => 'heroicon-o-x-circle',
                    default => 'heroicon-o-question-mark-circle',
                })
                ->iconColor($color)
                ->schema([
                    TextEntry::make("integrations.{$index}.response.success")
                        ->label(__('filament-form-builder::general.status'))
                        ->badge()
                        ->color($color)
                        ->formatStateUsing(fn ($state) => match ($state) {
                            true => __('filament-form-builder::general.success'),
                            false => __('filament-form-builder::general.failed'),
                            default => __('filament-form-builder::general.unknown'),
                        }),
                    KeyValueEntry::make("integrations.{$index}.response.response")
                        ->label(__('filament-form-builder::general.response'))
                        ->keyLabel(__('filament-form-builder::general.key'))
                        ->valueLabel(__('filament-form-builder::general.value')),
                ])
                ->columns(1)
                ->collapsible();
        }

        return Section::make(__('filament-form-builder::general.integration_responses'))
            ->icon('heroicon-o-server-stack')
            ->iconSize(IconSize::ExtraLarge)
            ->description(__('filament-form-builder::general.integration_responses_description'))
            ->schema($entries);
    }
}
