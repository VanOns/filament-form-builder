@use('Filament\Support\Icons\Heroicon')

<div class="ffb-answer-groups">
    @forelse ($groups as $group)
        <section class="ffb-answer-group">
            @if (filled($group['title']))
                <h3 class="ffb-answer-group-title">{{ $group['title'] }}</h3>
            @endif

            <dl class="ffb-answer-grid">
                @foreach ($group['fields'] as ['field' => $field, 'answer' => $answer, 'span' => $span, 'newRow' => $newRow])
                    <div
                        class="ffb-answer-cell"
                        style="--ffb-span: {{ $span }}{{ $newRow ? '; --ffb-start: 1' : '' }}"
                    >
                        <dt class="ffb-answer-cell-label">
                            {{ $field->getLabel() }}

                            @if ($field->isHidden())
                                <x-filament::badge color="gray" size="sm" :icon="Heroicon::OutlinedEyeSlash">
                                    {{ __('filament-form-builder::general.submission.hidden_field') }}
                                </x-filament::badge>
                            @endif
                        </dt>

                        <dd class="ffb-answer-cell-value">
                            @if ($answer === null)
                                <span class="ffb-answer-empty">—</span>
                            @else
                                @include('filament-form-builder::filament.submission.answer-value')
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @empty
        <p class="ffb-submission-empty">{{ __('filament-form-builder::general.submission.no_answers') }}</p>
    @endforelse

    @include('filament-form-builder::filament.submission.files-hint')
</div>
