<?php

namespace VanOns\FilamentFormBuilder\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use VanOns\FilamentFormBuilder\Enums\SubmitNotificationType;
use VanOns\FilamentFormBuilder\Http\Requests\CreateFormSubmission;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class FormSubmissionController
{
    public function store(
        int $formId,
        CreateFormSubmission $request
    ): RedirectResponse {
        $data = $request->except(['submitter_email', '_token', 'g-recaptcha-response']);
        if (!empty($request->allFiles())) {
            $data = array_merge($data, $this->mapFields($data));
        }

        $form = Form::query()->findOrFail($formId);

        FormSubmission::query()
            ->create([
                'form_id' => $form->id,
                'submitter_email' => $request->get('submitter_email'),
                'data' => $data,
            ]);

        if ($form->submit_notification_type === SubmitNotificationType::URL->value && !empty($form->submit_notification_content)) {
            $callBackUrl = $form->submit_notification_content;

            return redirect($callBackUrl);
        }

        return back(Response::HTTP_CREATED)->with([
            'submit_notification_type' => $form->submit_notification_type,
            'submit_notification_content' => $form->submit_notification_content,
        ]);
    }

    /**
     * @param array<string, mixed> $allFields
     * @return array<string, mixed>
     */
    protected function mapFields(array $allFields): array
    {
        $mappedFields = [];
        foreach ($allFields as $key => $value) {
            if (is_array($value)) {
                $mappedFields[$key] = $this->mapFields($value);
            } elseif ($value instanceof UploadedFile) {
                $mappedFields[$key] = route(
                    'filament-form-builder.form.download-file',
                    ['filePath' => $value->store('form_uploads', config('filament-form-builder.form-uploads-disk'))]
                );
            } else {
                $mappedFields[$key] = $value;
            }
        }

        return $mappedFields;
    }

    public function showFile(string $filePath): Response
    {
        $disk = config('filament-form-builder.form-uploads-disk', 'private');

        if (!\Storage::disk($disk)->exists($filePath)) {
            abort(404);
        }

        $mimeType = \Storage::disk($disk)->mimeType($filePath);

        return response(
            \Storage::disk($disk)->get($filePath),
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
            ]
        );
    }
}
