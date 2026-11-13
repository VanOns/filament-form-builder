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
        if ($this->form === null) {
            return '';
        }

        // With forms per site, a page copied to another site shows that site's form.
        $this->form = $this->form->inCurrentSite();

        return view($this->view, ['form' => $this->form]);
    }
}
