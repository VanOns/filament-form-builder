<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

use Illuminate\Support\HtmlString;
use VanOns\FilamentFormBuilder\Classes\MergeTags;
use VanOns\FilamentFormBuilder\Classes\SubmissionPlaceholders;
use VanOns\FilamentFormBuilder\Classes\SubmitNotification;
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
        return config('filament-form-builder.redirect_query', true) === true;
    }

    /**
     * Modify the redirect URL and/or notification message after a submission.
     * Both properties hold the values configured on the form.
     */
    public function modifySubmitNotification(FormSubmission $submission): void
    {
    }

    /**
     * Fill the properties from the first outcome whose conditions the answers
     * meet, then run the form type hook.
     */
    public function resolveSubmitNotification(FormSubmission $submission): static
    {
        $outcome = $this->getSubmitNotification($submission);
        $isRedirect = ($outcome['type'] ?? null) === SubmitNotificationType::URL->value;

        $this->redirectUrl = $outcome !== null && $isRedirect ? $this->resolveRedirectUrl($outcome, $submission) : null;
        $this->notificationMessage = $outcome !== null && !$isRedirect ? $this->renderNotificationMessage($outcome, $submission) : null;

        $this->modifySubmitNotification($submission);

        return $this;
    }

    /**
     * The outcome for a submission, skipping the kinds this type does not
     * allow.
     *
     * @return array{id: string, conditions: list<mixed>, conditionMatch: string, type: string, content: ?string, url: mixed, query: ?string}|null
     */
    public function getSubmitNotification(FormSubmission $submission): ?array
    {
        foreach ($this->form->getSubmitNotifications() as $outcome) {
            $isAllowed = $outcome['type'] === SubmitNotificationType::URL->value ? $this->hasRedirect() : $this->hasNotificationMessage();

            if ($isAllowed && SubmitNotification::holds($outcome, $submission->data ?? [])) {
                return $outcome;
            }
        }

        return null;
    }

    /**
     * The message with its tags filled in. The link to the submission is for
     * the panel only, so a visitor never gets it.
     *
     * @param  array<string, mixed>  $outcome
     */
    private function renderNotificationMessage(array $outcome, FormSubmission $submission): ?string
    {
        $placeholders = SubmissionPlaceholders::make($submission);

        $message = MergeTags::render($outcome['content'] ?? null, [
            ...$placeholders->values(),
            'all_fields' => new HtmlString($placeholders->allFieldsHtml()),
            'submission_url' => '',
        ]);

        return filled(strip_tags($message)) ? $message : null;
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    private function resolveRedirectUrl(array $outcome, FormSubmission $submission): ?string
    {
        $url = FilamentFormBuilderPlugin::resolveRedirectUrl($outcome['url'] ?? null, $this->form);
        $query = $this->hasSubmitNotificationQuery() ? ($outcome['query'] ?? null) : null;

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
