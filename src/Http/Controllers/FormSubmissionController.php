<?php

namespace VanOns\FilamentFormBuilder\Http\Controllers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use VanOns\FilamentFormBuilder\Classes\SubmissionMeta;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmitted;
use VanOns\FilamentFormBuilder\Forms\FormType;
use VanOns\FilamentFormBuilder\Http\Requests\CreateFormSubmission;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class FormSubmissionController
{
    public function store(CreateFormSubmission $request): mixed
    {
        $form = $request->getForm();
        $type = $form->getType();
        $keys = $form->getSubmittableKeys();

        $input = Arr::only(Arr::except($request->validationData(), array_keys($request->allFiles())), $keys);
        $uploads = Arr::only($request->allFiles(), $keys);
        $unsaved = fn (): FormSubmission => (new FormSubmission(['form_id' => $form->id, 'data' => $input]))->setRelation('form', $form);

        if ($request->isCaught()) {
            Log::info("The honeypot caught a submission of form {$form->getKey()}.");

            return $this->respond($type, $unsaved());
        }

        $repeat = $this->repeatKey($request, $input, $uploads);
        $seconds = (int) config('filament-form-builder.duplicate_seconds', 10);

        if ($seconds > 0 && ! Cache::add($repeat, 0, $seconds)) {
            // The first one may still be on its way; until it is stored, only its outcome can be shown.
            $first = FormSubmission::query()->find(Cache::get($repeat));

            if ($first === null) {
                return $this->respond($type, $unsaved());
            }

            return $type->response($first) ?? $this->respond($type, $first);
        }

        try {
            $data = $type->beforeStore($input);
            $files = $this->storeFiles($uploads);

            $submission = new FormSubmission([
                'form_id' => $form->id,
                'data' => $data,
                'files' => $files ?: null,
                // The page the form was on, the same one the redirect back goes to.
                'source_url' => $request->headers->get('referer'),
                'meta' => SubmissionMeta::capture($request),
            ]);
            $submission->setRelation('form', $form)->save();
        } catch (Throwable $exception) {
            // Sent again, it has to be stored after all.
            Cache::forget($repeat);

            throw $exception;
        }

        if ($seconds > 0) {
            Cache::put($repeat, $submission->getKey(), $seconds);
        }

        $type->afterSubmission($submission);
        FormSubmitted::dispatch($submission);

        return $type->response($submission) ?? $this->respond($type, $submission);
    }

    /**
     * The redirect or message the form is set up with, for the answers given.
     */
    protected function respond(FormType $type, FormSubmission $submission): mixed
    {
        $type->resolveSubmitNotification($submission);

        if (! empty($callBackUrl = $type->getRedirectUrl())) {
            return redirect($callBackUrl);
        }

        return back(Response::HTTP_CREATED)->with([
            'submit_notification_type' => $type->getNotificationType(),
            'submit_notification_content' => $type->getNotificationMessage(),
        ]);
    }

    /**
     * The same answers and files from the same visitor, as a double click sends them.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $uploads
     */
    protected function repeatKey(CreateFormSubmission $request, array $input, array $uploads): string
    {
        $files = array_map(
            fn (UploadedFile $file): array => [$file->getClientOriginalName(), $file->getSize()],
            array_filter(Arr::flatten($uploads), fn (mixed $file): bool => $file instanceof UploadedFile),
        );

        return 'filament-form-builder:submission:' . sha1(serialize([
            $request->getForm()->getKey(),
            $request->user()?->getAuthIdentifier() ?? $request->ip(),
            $input,
            $files,
        ]));
    }

    /**
     * Files live apart from the answers, so a visitor cannot pass off a typed
     * value as a stored file. Their size and type are kept, so showing them
     * needs no trip to the disk.
     *
     * @param array<string, mixed> $uploads
     * @return array<string, list<array{path: string, name: string, size: int, mime_type: ?string}>>
     */
    protected function storeFiles(array $uploads): array
    {
        $disk = FormSubmission::getFilesDisk();
        $files = [];

        foreach ($uploads as $key => $upload) {
            foreach (Arr::flatten(Arr::wrap($upload)) as $file) {
                if ($file instanceof UploadedFile && ($path = $file->store('form_uploads', $disk)) !== false) {
                    $files[$key][] = [
                        'path' => $path,
                        'name' => $file->getClientOriginalName(),
                        'size' => (int) $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                    ];
                }
            }
        }

        return $files;
    }

    public function showFile(Request $request, int $submissionId, string $key, int $index): StreamedResponse
    {
        $file = FormSubmission::query()->findOrFail($submissionId)->getFiles()[$key][$index] ?? abort(404);
        $disk = Storage::disk(FormSubmission::getFilesDisk());

        if (!$disk instanceof FilesystemAdapter || !$file->exists()) {
            abort(404);
        }

        $inline = !$request->boolean('download') && $file->opensInBrowser();

        return $disk->response(
            $file->path,
            $file->name,
            ['X-Content-Type-Options' => 'nosniff'],
            $inline ? 'inline' : 'attachment',
        );
    }
}
