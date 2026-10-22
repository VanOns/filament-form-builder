<ol class="ffb-progress ffb-progress-steps" aria-label="{{ __('filament-form-builder::general.steps.progress') }}" data-form-builder-progress hidden>
    @foreach ($steps as $index => $step)
        <li class="ffb-progress-step" data-form-builder-progress-step="{{ $index }}">
            <span class="ffb-sr-only" data-form-builder-progress-done hidden>{{ __('filament-form-builder::general.steps.completed') }}:</span>
            <button type="button" class="ffb-progress-step-title ffb-progress-step-link" data-form-builder-progress-link="{{ $index }}" hidden>{{ $step['title'] ?: __('filament-form-builder::general.steps.step') . ' ' . $loop->iteration }}</button>
            <span class="ffb-progress-step-title" data-form-builder-progress-text>{{ $step['title'] ?: __('filament-form-builder::general.steps.step') . ' ' . $loop->iteration }}</span>
        </li>
    @endforeach
</ol>
