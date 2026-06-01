<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

use VanOns\FilamentFormBuilder\Services\RecaptchaService;

trait HasRecaptcha
{
    public function hasRecaptcha(): bool
    {
        return $this->recaptcha ?? false;
    }

    public function getRecaptchaRules(): array
    {
        return $this->hasRecaptcha() && RecaptchaService::checkEnabled()
            ? RecaptchaService::getRules()
            : [];
    }
}
