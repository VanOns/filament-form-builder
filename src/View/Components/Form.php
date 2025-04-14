<?php

namespace VanOns\FilamentFormBuilder\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Component;
use VanOns\FilamentFormBuilder\Models\Form as FormModel;

class Form extends Component
{
    public function __construct(public ?FormModel $form) {}

    public function render(): View|Closure|string
    {
        if ($this->form && is_subclass_of($this->form->template, Component::class, true)) {
            return Blade::renderComponent(new ($this->form->template)($this->form));
        }

        return '';
    }
}
