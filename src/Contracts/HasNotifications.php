<?php

namespace VanOns\FilamentFormBuilder\Contracts;

use VanOns\FilamentFormBuilder\Classes\EmailNotification;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

interface HasNotifications
{
    public static function hasNotifications(): bool;

    public static function triggerNotifications(FormSubmission $submission): void;

    /**
     * @param FormSubmission $submission
     * @return array<EmailNotification>
     */
    public static function getNotifications(FormSubmission $submission): array;

    public static function sendNotifications(FormSubmission $submission): void;

    public static function sendNotification(EmailNotification $notification, FormSubmission $submission): void;

}