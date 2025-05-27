<?php

namespace VanOns\FilamentFormBuilder\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use VanOns\FilamentFormBuilder\Models\Form as FormModel;

class CustomFormRenderer extends Component
{
    protected string $defaultView = 'filament-form-builder::components.custom-form-renderer';

    public function __construct(
        public ?FormModel $form,
        public ?string $view = null,
    ) {
        $this->view ??= $this->defaultView;
    }

    public function render(): View|Closure|string
    {
        return view($this->view, [
            'form' => $this->form,
        ]);
    }
}
