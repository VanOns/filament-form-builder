<?php

use Illuminate\Support\Facades\Route;
use VanOns\FilamentFormBuilder\Http\Controllers\FormSubmissionController;

Route::name('filament-form-builder.')
    ->prefix('filament-form-builder')
    ->middleware(
        [
            'throttle:filament-form-builder-submissions',
            ...config('filament-form-builder.form-middleware', []),
        ]
    )
    ->group(function () {
        Route::post('{formId}/submit', [FormSubmissionController::class, 'store'])
            ->name('form.store');
    });
