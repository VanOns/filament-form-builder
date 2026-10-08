<ol class="ffb-progress ffb-progress-steps" data-form-builder-progress hidden>
    @foreach ($steps as $index => $step)
        <li class="ffb-progress-step" data-form-builder-progress-step="{{ $index }}">
            <span class="ffb-progress-step-title">{{ $step['title'] }}</span>
        </li>
    @endforeach
</ol>
