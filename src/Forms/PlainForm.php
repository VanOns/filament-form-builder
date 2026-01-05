<?php

namespace VanOns\FilamentFormBuilder\Forms;

use VanOns\FilamentFormBuilder\Contracts\FilamentForm;
use VanOns\FilamentFormBuilder\Traits\IsFilamentForm;

abstract class PlainForm implements FilamentForm
{
    use IsFilamentForm;
}
