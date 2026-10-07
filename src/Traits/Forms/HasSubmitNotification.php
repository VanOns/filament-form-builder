<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

use Illuminate\Support\HtmlString;
use VanOns\FilamentFormBuilder\Classes\MergeTags;
use VanOns\FilamentFormBuilder\Classes\SubmissionPlaceholders;
use VanOns\FilamentFormBuilder\Enums\SubmitNotificationType;
use VanOns\FilamentFormBuilder\FilamentFormBuilderPlugin;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

trait HasSubmitNotification
{
    protected ?string $redirectUrl = null;

    protected ?string $notificationMessage = null;

    /**
     * Whether the redirect URL is configurable in the admin. Disable it to
     * hard-code the URL in `modifySubmitNotification()`.
     */
    public function hasRedirect(): bool
    {
        return true;
    }

    /**
     * Whether the notification message is configurable in the admin. Disable it
     * to hard-code the message in `modifySubmitNotification()`.
     */
    public function hasNotificationMessage(): bool
    {
        return true;
    }

    /**
     * Whether the query string field is configurable in the admin. Disable it to
     * keep submitted values out of the redirect URL.
     */
    public function hasSubmitNotificationQuery(): bool
    {
        return config('filament-form-builder.submit_notification_query_enabled', true) === true;
    }

    /**
     * Modify the redirect URL and/or notification message after a submission.
     * Both properties hold the values configured on the form.
     */
    public function modifySubmitNotification(FormSubmission $submission): void
    {
    }

    /**
     * Fill the properties with the configured values, then run the form type hook.
     */
    public function resolveSubmitNotification(FormSubmission $submission): static
    {
        $this->redirectUrl = $this->resolveRedirectUrl($submission);

        $this->notificationMessage = $this->hasNotificationMessage()
            ? $this->renderNotificationMessage($submission)
            : null;

        $this->modifySubmitNotification($submission);

        return $this;
    }

    /**
     * The message with its tags filled in. The link to the submission is for
     * the panel only, so a visitor never gets it.
     */
    private function renderNotificationMessage(FormSubmission $submission): ?string
    {
        $placeholders = SubmissionPlaceholders::make($submission);

        $message = MergeTags::render($this->form->submit_notification_content, [
            ...$placeholders->values(),
            'all_fields' => new HtmlString($placeholders->allFieldsHtml()),
            'submission_url' => '',
        ]);

        return filled(strip_tags($message)) ? $message : null;
    }

    private function resolveRedirectUrl(FormSubmission $submission): ?string
    {
        if (!$this->hasRedirect() || $this->form->submit_notification_type !== SubmitNotificationType::URL->value) {
            return null;
        }

        $url = FilamentFormBuilderPlugin::resolveRedirectUrl($this->form->submit_notification_url, $this->form);

        $query = $this->hasSubmitNotificationQuery()
            ? $this->form->submit_notification_query
            : null;

        return SubmissionPlaceholders::make($submission)->appendQuery($url, $query);
    }

    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }

    public function getNotificationMessage(): ?string
    {
        return $this->notificationMessage;
    }

    /**
     * The type of the resolved notification, or null when there is nothing to show.
     */
    public function getNotificationType(): ?string
    {
        if (filled($this->getNotificationMessage())) {
            return SubmitNotificationType::Content->value;
        }

        return filled($this->getRedirectUrl())
            ? SubmitNotificationType::URL->value
            : null;
    }
}
