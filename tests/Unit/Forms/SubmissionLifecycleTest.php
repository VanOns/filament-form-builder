<?php

use Illuminate\Support\Facades\Queue;
use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\Models\FormSubmissionNotificationLog;
use VanOns\FilamentFormBuilder\View\Components\Forms\CustomForm;

class SilentForm extends CustomForm
{
    public static function hasNotifications(): bool
    {
        return false;
    }
}

class RecordingIntegration extends Integration
{
    public function handle(): void
    {
        $this->setResponse(['submission' => $this->formSubmission->id])->setSuccess(true);
    }
}

function lifecycleForm(string $template = CustomForm::class): Form
{
    return Form::create([
        'title' => 'Contact ' . uniqid(),
        'template' => $template,
        'notifications' => [['subject' => 'Nieuw', 'content' => 'Hoi', 'receivers' => ['info@example.test']]],
        'integrations' => [['class' => RecordingIntegration::class]],
    ]);
}

beforeEach(function () {
    Queue::fake();
    config([
        'filament-form-builder.email_notification_enabled' => true,
        'filament-form-builder.integrations' => [RecordingIntegration::class],
    ]);
});

it('sends the notifications of a form', function () {
    FormSubmission::create(['form_id' => lifecycleForm()->id, 'data' => []]);

    expect(FormSubmissionNotificationLog::count())->toBe(1);
});

it('sends nothing for a template that turned notifications off', function () {
    FormSubmission::create(['form_id' => lifecycleForm(SilentForm::class)->id, 'data' => []]);

    expect(FormSubmissionNotificationLog::count())->toBe(0);
});

it('keeps the integration responses of one submission out of the next', function () {
    $form = lifecycleForm();

    $first = FormSubmission::create(['form_id' => $form->id, 'data' => []]);
    $second = FormSubmission::create(['form_id' => $form->id, 'data' => []]);

    expect($first->fresh()->integrations)->toHaveCount(1)
        ->and($second->fresh()->integrations)->toHaveCount(1)
        ->and($second->fresh()->integrations[0]['response']['response']['submission'])->toBe($second->id);
});
