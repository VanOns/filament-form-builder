<?php

namespace VanOns\FilamentFormBuilder\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use VanOns\FilamentFormBuilder\Http\Requests\CreateFormSubmission;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class FormSubmissionController
{
    public function store(
        int $formId,
        CreateFormSubmission $request
    ): RedirectResponse {
        $form = Form::query()->findOrFail($formId);

        $callBackUrl = $request->get('callback_url');

        FormSubmission::query()
            ->create([
                'form_id' => $form->id,
                'submitter_email' => $request->get('submitter_email'),
                'data' => $request->except(['submitter_email', '_token', 'callback_url', 'g-recaptcha-response']),
            ]);

        if ($callBackUrl) {
            return redirect($callBackUrl)->with([
                'submit_notification_type' => $form->submit_notification_type,
                'submit_notification_content' => $form->submit_notification_content,
            ]);
        }

        return back(Response::HTTP_CREATED)->with([
            'submit_notification_type' => $form->submit_notification_type,
            'submit_notification_content' => $form->submit_notification_content,
        ]);
    }
}
