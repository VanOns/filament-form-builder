{{--
    The settings are not live: after a change in the slide-over the preview asks
    for itself again, which takes the change along. A toggle only clicks, it
    sends no input or change.
--}}
<div
    class="ffb-settings-preview"
    x-data="{
        queued: null,
        refresh(event) {
            if (event.target.closest('.fi-modal') !== $el.closest('.fi-modal')) {
                return
            }

            clearTimeout(this.queued)
            this.queued = setTimeout(() => $wire.partiallyRenderSchemaComponent(@js($getKey())), 400)
        },
    }"
    x-on:input.window="refresh($event)"
    x-on:change.window="refresh($event)"
    x-on:click.window="refresh($event)"
>
    <p class="ffb-settings-preview-heading">{{ __('filament-form-builder::general.canvas.preview') }}</p>

    @include($field->getPreviewView(), ['field' => $field])
</div>
