<div class="ffb-preview">
    @include('filament-form-builder::filament.previews.partials.label')

    @include('filament-form-builder::filament.previews.partials.value', ['class' => 'ffb-preview-textarea'])
    @include('filament-form-builder::filament.previews.partials.description')
    <div class="ffb-preview-key">{{ $field->getKey() }}</div>
</div>
