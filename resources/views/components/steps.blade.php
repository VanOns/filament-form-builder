@props([
    'form',
])

@php
    /** @var \VanOns\FilamentFormBuilder\Models\Form $form */
    $settings = $form->getStepSettings();
    $steps = $form->getSteps();
    [$submitFields, $endFields] = collect($form->getEndFields())
        ->partition(fn ($field): bool => $field instanceof \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\SubmitField);
@endphp

{{-- Without form-builder.js every step shows and the buttons between them stay hidden. --}}
<div
    class="ffb-steps"
    data-form-builder-steps
    data-step-of="{{ __('filament-form-builder::general.steps.step_of', ['current' => ':current', 'total' => ':total']) }}"
>
    @if ($settings['progress'] !== \VanOns\FilamentFormBuilder\Enums\StepProgress::None->value)
        @include("filament-form-builder::components.steps.progress-{$settings['progress']}", ['steps' => $steps])
    @endif

    <p class="ffb-sr-only" aria-live="polite" data-form-builder-step-status></p>

    @foreach ($steps as $index => $step)
        <fieldset
            class="ffb-step"
            tabindex="-1"
            data-form-builder-step="{{ $index }}"
            @if (filled($step['title'])) data-title="{{ $step['title'] }}" @endif
        >
            @if (filled($step['title']))
                <legend class="ffb-step-title" tabindex="-1">{{ $step['title'] }}</legend>
            @endif

            @foreach ($step['fields'] as $field)
                {!! $field->render() !!}
            @endforeach
        </fieldset>
    @endforeach

    @if ($endFields->isNotEmpty())
        <div class="ffb-step-end" data-form-builder-steps-end>
            @foreach ($endFields as $field)
                {!! $field->render() !!}
            @endforeach
        </div>
    @endif

    <div class="ffb-step-nav">
        <button type="button" class="ffb-step-button ffb-step-previous" data-form-builder-previous hidden>
            {{ $settings['previous'] ?? __('filament-form-builder::general.steps.previous') }}
        </button>
        <button type="button" class="ffb-step-button ffb-step-next" data-form-builder-next hidden>
            {{ $settings['next'] ?? __('filament-form-builder::general.steps.next') }}
        </button>

        @if ($submitFields->isNotEmpty())
            <div class="ffb-step-submit" data-form-builder-steps-end>
                @foreach ($submitFields as $field)
                    {!! $field->render() !!}
                @endforeach
            </div>
        @endif
    </div>
</div>
