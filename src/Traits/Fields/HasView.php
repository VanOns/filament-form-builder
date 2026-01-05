<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

trait HasView
{
    public static string $view = '';

    public function getView(): string
    {
        return static::$view;
    }
}
