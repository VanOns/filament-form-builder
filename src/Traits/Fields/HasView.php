<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

trait HasView
{
    public static string $view = '';

    public static string $previewView = 'filament-form-builder::filament.previews.field';

    public function getView(): string
    {
        return static::$view;
    }

    public function getPreviewView(): string
    {
        return static::$previewView;
    }
}
