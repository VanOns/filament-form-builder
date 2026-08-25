<?php

namespace VanOns\FilamentFormBuilder\Http\Controllers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use VanOns\FilamentFormBuilder\Contracts\FilamentForm;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\Http\Requests\CreateFormSubmission;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class FormSubmissionController
{
    public function store(
        int $formId,
        CreateFormSubmission $request
    ): mixed {
        $data = $request->except(['submitter_email', '_token', 'g-recaptcha-response']);
        if (!empty($request->allFiles())) {
            $data = array_merge($data, $this->mapFields($data));
        }

        $form = Form::query()->findOrFail($formId);

        /** @var FilamentForm $template */
        $template = $form->template;

        $data = $template::modifyDataUsing($data, $form);

        $submitterEmail = $request->input('submitter_email');
        if (empty($submitterEmail) && !empty($emailData = Arr::only($data, static::getPossibleEmailFields()))) {
            $submitterEmail = head($emailData);
        }

        $submission = FormSubmission::query()
            ->create([
                'form_id' => $form->id,
                'submitter_email' => $submitterEmail,
                'data' => $data,
            ]);

        if ($response = $template::successResponse($submission)) {
            return $response;
        }

        $formComponent = $form->getFormComponent()->resolveSubmitNotification($submission);

        if (!empty($callBackUrl = $formComponent->getRedirectUrl())) {
            return redirect($callBackUrl);
        }

        return back(Response::HTTP_CREATED)->with([
            'submit_notification_type' => $formComponent->getNotificationType(),
            'submit_notification_content' => $formComponent->getNotificationMessage(),
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

        if (!Storage::disk($disk)->exists($filePath)) {
            abort(404);
        }

        $filesystem = Storage::disk($disk);
        $mimeType = $filesystem instanceof FilesystemAdapter
            ? $filesystem->mimeType($filePath)
            : 'application/octet-stream';

        return response(
            $filesystem->get($filePath),
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
            ]
        );
    }

    /**
     * @return array<string>
     */
    public static function getPossibleEmailFields(): array
    {
        $possibleEmailFields = [
            'email',
            'e-mail',
            'emailadres',
            'email_adres',
            'e-mail_adres',
            'e-mail_address',
            'mail_address',
        ];

        return array_merge(
            $possibleEmailFields,
            array_map(fn ($field) => FormField::$keyPrefix . $field, $possibleEmailFields)
        );
    }
}
