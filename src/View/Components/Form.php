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

        $formComponent = $this->form->getFormComponent();
        if (is_subclass_of($this->form->template, FormComponent::class) && method_exists($formComponent, 'render')) {
            return $formComponent->render();
        } else {
            return '';
        }
    }
}
