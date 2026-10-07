<?php

use Illuminate\Support\Facades\Queue;
use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Forms\CustomForm;
use VanOns\FilamentFormBuilder\Jobs\RunFormIntegrationsJob;
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
    public function handle(): void
    {
        $this->setResponse(['submission' => $this->formSubmission->id])->setSuccess(true);
    }
}

function lifecycleForm(string $type = 'custom'): Form
{
    return Form::create([
        'title' => 'Contact ' . uniqid(),
        'template' => $type,
        'notifications' => [['subject' => 'Nieuw', 'content' => 'Hoi', 'receivers' => ['info@example.test']]],
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
    Queue::assertPushed(RunFormIntegrationsJob::class);
});

it('sends nothing for a submission created in code', function () {
    FormSubmission::create(['form_id' => lifecycleForm()->id, 'data' => []]);

    expect(FormSubmissionNotificationLog::count())->toBe(0);
    Queue::assertNotPushed(RunFormIntegrationsJob::class);
});

it('sends nothing for a form type that turned notifications off', function () {
    postTo(lifecycleForm('silent'));

    expect(FormSubmissionNotificationLog::count())->toBe(0);
});

it('keeps the integration responses of one submission out of the next', function () {
    $form = lifecycleForm();

    $first = FormSubmission::create(['form_id' => $form->id, 'data' => []]);
    $second = FormSubmission::create(['form_id' => $form->id, 'data' => []]);

    (new RunFormIntegrationsJob($first))->handle();
    (new RunFormIntegrationsJob($second))->handle();

    expect($first->fresh()->integrations)->toHaveCount(1)
        ->and($second->fresh()->integrations)->toHaveCount(1)
        ->and($second->fresh()->integrations[0]['response']['response']['submission'])->toBe($second->id);
});

it('remembers when each integration ran', function () {
    $this->travelTo(now()->setDateTime(2026, 10, 7, 14, 33, 5));
    $submission = FormSubmission::create(['form_id' => lifecycleForm()->id, 'data' => []]);

    (new RunFormIntegrationsJob($submission))->handle();

    expect($submission->fresh()->integrations[0]['ran_at'])->toBe(now()->toIso8601String());
});
