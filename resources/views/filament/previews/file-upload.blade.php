<div class="ffb-preview">
    @include('filament-form-builder::filament.previews.partials.label')

    <div class="ffb-preview-input ffb-preview-dropzone"></div>
    @include('filament-form-builder::filament.previews.partials.description')
    <div class="ffb-preview-key">{{ $field->getKey() }}</div>
</div>
