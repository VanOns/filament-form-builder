<div class="ffb-settings-preview">
    <p class="ffb-settings-preview-heading">{{ __('filament-form-builder::general.canvas.preview') }}</p>

    @include($field->getPreviewView(), ['field' => $field])
</div>
