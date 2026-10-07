@props(['form'])

@if ($honeypot = $form->getHoneypot())
    {{-- Out of sight and out of reach: a person never fills it in, a bot that fills in every field does. --}}
    <div aria-hidden="true" style="position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden;">
        <label>
            Website
            <input type="text" name="{{ $honeypot['field'] }}" value="" tabindex="-1" autocomplete="off">
        </label>
    </div>
    <input type="hidden" name="{{ $honeypot['tokenField'] }}" value="{{ $honeypot['token'] }}">
@endif
