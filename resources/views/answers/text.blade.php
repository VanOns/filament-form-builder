@use('Illuminate\Support\Arr')
@use('Illuminate\Support\Str')
@use('VanOns\FilamentFormBuilder\Models\FormSubmission')

@if (is_array($value) && array_is_list($value))
    <span class="ffb-answer-badges">
        @foreach (Arr::flatten($value) as $item)
            <x-filament::badge>{{ $item }}</x-filament::badge>
        @endforeach
    </span>
@elseif (is_array($value))
    <dl class="ffb-answer-columns">
        @foreach ($value as $key => $item)
            <div>
                <dt>{{ Str::headline((string) $key) }}</dt>
                <dd>{{ FormSubmission::toText($item) }}</dd>
            </div>
        @endforeach
    </dl>
@else
    {{-- Only http(s) links become anchors, so a typed javascript: URL stays text. --}}
    <span class="ffb-answer-text">{!! preg_replace(
        '~https?://[^\s<]*[^\s<.,;:!?)\]]~',
        '<a href="$0" target="_blank" rel="noopener noreferrer">$0</a>',
        e(FormSubmission::toText($value)),
    ) !!}</span>
@endif
