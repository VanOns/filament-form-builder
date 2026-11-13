@use('Filament\Support\Icons\Heroicon')
@use('Illuminate\Support\Carbon')

<div class="ffb-preview">
    @include('filament-form-builder::filament.previews.partials.label')

    <div class="ffb-preview-input ffb-preview-date">
        <span>{{ Carbon::now()->translatedFormat($field->getFormat()) }}</span>
        <x-filament::icon :icon="Heroicon::OutlinedCalendarDays" class="ffb-preview-date-icon" />
    </div>
    @include('filament-form-builder::filament.previews.partials.description')
    <div class="ffb-preview-key">{{ $field->getKey() }}</div>
</div>
