<dl class="ffb-answers">
    @forelse ($answers as $answer)
        <div class="ffb-answer">
            <dt class="ffb-answer-label">
                <x-filament::icon :icon="$answer->icon" class="ffb-answer-icon" />

                <span class="ffb-answer-heading">
                    <span class="ffb-answer-title">
                        {{ $answer->label }}

                        @if ($answer->badge !== null)
                            <x-filament::badge color="gray" size="sm">{{ $answer->badge }}</x-filament::badge>
                        @endif
                    </span>

                    <span class="ffb-answer-meta">
                        <code>{{ $answer->key }}</code>@if ($answer->note !== null) · {{ $answer->note }}@endif
                    </span>
                </span>
            </dt>

            <dd class="ffb-answer-value">
                @include($answer->view, [
                    'value' => $answer->value,
                    'raw' => $answer->raw,
                    'field' => $answer->field,
                    'submission' => $submission,
                    'answer' => $answer,
                ])
            </dd>
        </div>
    @empty
        <p class="ffb-submission-empty">{{ __('filament-form-builder::general.submission.no_answers') }}</p>
    @endforelse
</dl>
