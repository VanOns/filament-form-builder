<?php

use Illuminate\Support\Facades\Queue;
use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Forms\CustomForm;
use VanOns\FilamentFormBuilder\Jobs\RunFormIntegrationJob;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\Models\FormSubmissionNotificationLog;

class SilentForm extends CustomForm
{
    public function hasNotifications(): bool
    {
        return false;
    }
}

class RecordingIntegration extends Integration
{
}

function lifecycleForm(string $type = 'custom'): Form
{
    return Form::create([
        'title' => 'Contact ' . uniqid(),
        'template' => $type,
        'notifications' => [['subject' => 'Nieuw', 'content' => 'Hoi', 'to' => ['info@example.test']]],
        'integrations' => [['class' => RecordingIntegration::class]],
    ]);
}

function postTo(Form $form): FormSubmission
{
    test()->post(route('filament-form-builder.form.store', ['formId' => $form->id]));

    return FormSubmission::query()->where('form_id', $form->id)->latest('id')->firstOrFail();
}

beforeEach(function () {
    Queue::fake();
    config([
        'filament-form-builder.email_notifications' => true,
        'filament-form-builder.integrations' => [RecordingIntegration::class],
        'filament-form-builder.types.silent' => SilentForm::class,
    ]);
});

it('sends the notifications of a form and queues its integrations', function () {
    postTo(lifecycleForm());

    expect(FormSubmissionNotificationLog::count())->toBe(1);
    Queue::assertPushed(RunFormIntegrationJob::class, 1);
});

it('sends nothing for a submission created in code', function () {
    FormSubmission::create(['form_id' => lifecycleForm()->id, 'data' => []]);

    expect(FormSubmissionNotificationLog::count())->toBe(0);
    Queue::assertNotPushed(RunFormIntegrationJob::class);
});

it('sends nothing for a form type that turned notifications off', function () {
    postTo(lifecycleForm('silent'));

    expect(FormSubmissionNotificationLog::count())->toBe(0);
});
