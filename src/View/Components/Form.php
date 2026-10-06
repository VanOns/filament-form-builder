<?php

namespace VanOns\FilamentFormBuilder\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use VanOns\FilamentFormBuilder\Models\Form as FormModel;

class Form extends Component
{
    public function __construct(
        public ?FormModel $form,
        public string $view = 'filament-form-builder::components.form',
    ) {
    }

    public function render(): View|Closure|string
    {
        return $this->form === null ? '' : view($this->view, ['form' => $this->form]);
    }
}
