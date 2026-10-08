<?php

use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\Route;
use VanOns\FilamentFormBuilder\Http\Controllers\FormSubmissionController;

Route::name('filament-form-builder.')
    ->prefix('filament-form-builder')
    ->group(function () {
        Route::post('{formId}/submit', [FormSubmissionController::class, 'store'])
            ->middleware(
                [
                    'throttle:filament-form-builder-submissions',
                    // Without it, a check of one step would be stored as a submission.
                    HandlePrecognitiveRequests::class,
                    ...config('filament-form-builder.form_middleware', []),
                ]
            )
            ->name('form.store');
        Route::get('submissions/{submissionId}/files/{key}/{index}', [FormSubmissionController::class, 'showFile'])
            ->middleware([
                ValidateSignature::class,
                ...config('filament-form-builder.uploads.middleware', []),
            ])
            ->whereNumber(['submissionId', 'index'])
            ->name('form.download-file');
    });
