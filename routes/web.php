<?php

use Illuminate\Support\Facades\Route;
use VanOns\FilamentFormBuilder\Http\Controllers\FormSubmissionController;

Route::name('filament-form-builder.')
    ->prefix('filament-form-builder')
    ->group(function () {
        Route::post('{formId}/submit', [FormSubmissionController::class, 'store'])
            ->middleware(
                [
                    'throttle:filament-form-builder-submissions',
                    ...config('filament-form-builder.form-middleware', []),
                ]
            )
            ->name('form.store');
        Route::get('file/{filePath}', [FormSubmissionController::class, 'showFile'])
            ->middleware(config('filament-form-builder.form-uploads-middleware'))
            ->where('filePath', '.*')
            ->name('form.download-file');
    });
