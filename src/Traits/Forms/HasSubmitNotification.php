<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

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
    public static function hasRedirect(): bool
    {
        return true;
    }

    /**
     * Whether the notification message is configurable in the admin. Disable it
     * to hard-code the message in `modifySubmitNotification()`.
     */
    public static function hasNotificationMessage(): bool
    {
        return true;
    }

    /**
     * Modify the redirect URL and/or notification message after a submission.
     * Both properties hold the values configured on the form.
     */
    public function modifySubmitNotification(FormSubmission $submission): void
    {
    }

    /**
     * Fill the properties with the configured values, then run the template hook.
     */
    public function resolveSubmitNotification(FormSubmission $submission): static
    {
        $this->redirectUrl = static::hasRedirect() && $this->form->submit_notification_type === SubmitNotificationType::URL->value
            ? FilamentFormBuilderPlugin::resolveRedirectUrl($this->form->submit_notification_url, $this->form)
            : null;

        $this->notificationMessage = static::hasNotificationMessage()
            ? $this->form->submit_notification_content
            : null;

        $this->modifySubmitNotification($submission);

        return $this;
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
