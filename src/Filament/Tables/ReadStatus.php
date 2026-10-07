<?php

namespace VanOns\FilamentFormBuilder\Filament\Tables;

use Filament\Actions\BulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

/**
 * Which submissions someone has opened, the same in every submissions table:
 * an envelope and bold text on the ones nobody has, a filter and bulk actions.
 */
final class ReadStatus
{
    public static function apply(Table $table): Table
    {
        return $table->recordClasses(fn (FormSubmission $record): ?string => $record->isRead() ? null : 'ffb-unread');
    }

    public static function column(): IconColumn
    {
        return IconColumn::make('unread')
            ->label('')
            ->state(fn (FormSubmission $record): bool => !$record->isRead())
            ->icon(fn (bool $state): ?Heroicon => $state ? Heroicon::OutlinedEnvelope : null)
            ->color('primary')
            ->tooltip(fn (bool $state): ?string => $state ? __('filament-form-builder::general.submission.unread') : null)
            ->width('1%');
    }

    public static function filter(): TernaryFilter
    {
        return TernaryFilter::make('read_at')
            ->label(__('filament-form-builder::general.submission.read_filter'))
            ->nullable()
            ->trueLabel(__('filament-form-builder::general.submission.read'))
            ->falseLabel(__('filament-form-builder::general.submission.unread'));
    }

    /**
     * @return array<BulkAction>
     */
    public static function bulkActions(): array
    {
        $mark = fn (string $name, bool $isRead, Heroicon $icon): BulkAction => BulkAction::make($name)
            ->label(__("filament-form-builder::general.submission.{$name}"))
            ->icon($icon)
            ->action(function (Collection $records) use ($isRead): void {
                /** @var Collection<int, FormSubmission> $records */
                $records->each(fn (FormSubmission $record) => $record->markAsRead($isRead));
            })
            ->deselectRecordsAfterCompletion();

        return [
            $mark('mark_read', true, Heroicon::OutlinedEnvelopeOpen),
            $mark('mark_unread', false, Heroicon::OutlinedEnvelope),
        ];
    }
}
