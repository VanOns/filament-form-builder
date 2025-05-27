<?php

namespace VanOns\FilamentFormBuilder\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use VanOns\FilamentFormBuilder\Models\Form as FormModel;

class Form extends Component
{
    public function __construct(public ?FormModel $form)
    {
    }

    public function render(): View|Closure|string
    {
        if (!isset($this->form)) {
            return '';
        }

        if (is_subclass_of($this->form->template, FormComponent::class)) {
            return $this->form->getFormComponent()->render();
        } else {
            return '';
        }
    }
}
