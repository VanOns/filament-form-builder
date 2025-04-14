<?php

namespace VanOns\FilamentFormBuilder\Events\Form;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use VanOns\FilamentFormBuilder\Models\Form;

class FormForceDeleted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Form $form)
    {
    }
}
