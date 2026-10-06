<?php

namespace VanOns\FilamentFormBuilder\Http\Controllers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use VanOns\FilamentFormBuilder\Http\Requests\CreateFormSubmission;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class FormSubmissionController
{
    public function store(CreateFormSubmission $request): mixed
    {
        $form = $request->getForm();
        $type = $form->getType();
        $keys = $form->getSubmittableKeys();

        $data = $type->beforeStore(Arr::only($request->input(), $keys));
        $files = $this->storeFiles(Arr::only($request->allFiles(), $keys));

        $submission = FormSubmission::query()
            ->create([
                'form_id' => $form->id,
                'submitter_email' => $form->findSubmitterEmail($data),
                'data' => $data,
                'files' => $files ?: null,
            ]);

        $type->afterSubmission($submission);

        if ($response = $type->response($submission)) {
            return $response;
        }

        $type->resolveSubmitNotification($submission);

        if (!empty($callBackUrl = $type->getRedirectUrl())) {
            return redirect($callBackUrl);
        }

        return back(Response::HTTP_CREATED)->with([
            'submit_notification_type' => $type->getNotificationType(),
            'submit_notification_content' => $type->getNotificationMessage(),
        ]);
    }

    /**
     * Files live apart from the answers, so a visitor cannot pass off a typed
     * value as a stored file.
     *
     * @param array<string, mixed> $uploads
     * @return array<string, list<array{path: string, name: string}>>
     */
    protected function storeFiles(array $uploads): array
    {
        $disk = FormSubmission::getFilesDisk();
        $files = [];

        foreach ($uploads as $key => $upload) {
            foreach (Arr::flatten(Arr::wrap($upload)) as $file) {
                if ($file instanceof UploadedFile && ($path = $file->store('form_uploads', $disk)) !== false) {
                    $files[$key][] = ['path' => $path, 'name' => $file->getClientOriginalName()];
                }
            }
        }

        return $files;
    }

    public function showFile(int $submissionId, string $key, int $index): StreamedResponse
    {
        $file = FormSubmission::query()->findOrFail($submissionId)->getFiles()[$key][$index] ?? abort(404);
        $disk = Storage::disk(FormSubmission::getFilesDisk());

        if (!$disk instanceof FilesystemAdapter || !$disk->exists($file->path)) {
            abort(404);
        }

        // Only types that cannot run script on this domain open in the browser.
        $mimeType = $disk->mimeType($file->path) ?: 'application/octet-stream';
        $inline = $mimeType === 'application/pdf' || (str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml');

        return $disk->response(
            $file->path,
            $file->name,
            ['X-Content-Type-Options' => 'nosniff'],
            $inline ? 'inline' : 'attachment',
        );
    }
}
