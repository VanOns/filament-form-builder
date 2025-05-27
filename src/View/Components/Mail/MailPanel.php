<?php

namespace VanOns\FilamentFormBuilder\View\Components\Mail;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class MailPanel extends Component
{
    public static string $view = 'filament-form-builder::components.mail.panel';

    public function render(): View|Closure|string
    {
        return view(static::$view);
    }
}
