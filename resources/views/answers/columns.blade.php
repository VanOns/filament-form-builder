<dl class="ffb-answer-columns">
    @foreach ($answer->columns as $key => $label)
        @continue(! array_key_exists($key, $value))

        <div>
            <dt>{{ $label }}</dt>
            <dd>@include('filament-form-builder::answers.text', ['value' => $value[$key]])</dd>
        </div>
    @endforeach
</dl>
