{{-- The URL and the parameters are not live: after a change to the outcome it asks for itself again, which takes the change along. --}}
<div
    x-data="{
        queued: null,
        refresh(event) {
            if (! $el.closest('[data-ffb-outcome]')?.contains(event.target)) {
                return
            }

            clearTimeout(this.queued)
            this.queued = setTimeout(() => $wire.partiallyRenderSchemaComponent(@js($getKey())), 400)
        },
    }"
    x-on:input.window="refresh($event)"
    x-on:change.window="refresh($event)"
>
    {{ $example }}
</div>
