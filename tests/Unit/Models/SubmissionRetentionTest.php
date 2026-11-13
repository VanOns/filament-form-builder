<?php

use Filament\Forms\Components\Select;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\RelationManagers\FormSubmissionsRelationManager;
use VanOns\FilamentFormBuilder\FilamentFormBuilderProvider;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

function keptForm(string $title, ?int $retention = null): Form
{
    return Form::create(['title' => $title, 'template' => 'custom', 'retention_months' => $retention, 'submit_notifications' => [['type' => 'content', 'content' => '<p>Bedankt!</p>']]]);
}

function submittedMonthsAgo(Form $form, int $months, array $attributes = []): FormSubmission
{
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['naam' => $form->title . ' ' . $months], ...$attributes]);
    $submission->forceFill(['created_at' => now()->subMonthsNoOverflow($months)->subDay()])->saveQuietly();

    return $submission;
}

/**
 * @return list<string>
 */
function prunedAndLeft(): array
{
    test()->artisan('model:prune', ['--model' => [FormSubmission::class]])->assertSuccessful();

    return FormSubmission::withTrashed()->orderBy('id')->get()->map(fn (FormSubmission $submission): string => $submission->data['naam'])->all();
}

it('keeps every submission unless a retention is set', function () {
    submittedMonthsAgo(keptForm('Contact'), 60);

    expect(prunedAndLeft())->toBe(['Contact 60']);
});

it('deletes what is older than the retention, with its files and when trashed', function () {
    Storage::fake('local');
    Storage::disk('local')->put('form_uploads/cv.pdf', 'pdf');
    config(['filament-form-builder.retention_months' => 12]);
    $form = keptForm('Contact');

    submittedMonthsAgo($form, 13, ['files' => ['cv' => [['path' => 'form_uploads/cv.pdf', 'name' => 'cv.pdf']]]]);
    submittedMonthsAgo($form, 14)->delete();
    submittedMonthsAgo($form, 11);

    expect(prunedAndLeft())->toBe(['Contact 11']);
    Storage::disk('local')->assertMissing('form_uploads/cv.pdf');
});

it('lets a form keep its submissions shorter, longer or forever', function () {
    config(['filament-form-builder.retention_months' => 12]);

    foreach ([keptForm('Standaard'), keptForm('Sollicitatie', 1), keptForm('Offerte', 24), keptForm('Archief', 0)] as $form) {
        submittedMonthsAgo($form, 2);
        submittedMonthsAgo($form, 13);
    }

    expect(prunedAndLeft())->toBe(['Standaard 2', 'Offerte 2', 'Offerte 13', 'Archief 2', 'Archief 13']);
});

it('prunes every night', function () {
    $schedule = new Schedule();
    app()->instance(Schedule::class, $schedule);

    (new FilamentFormBuilderProvider(app()))->hasPruning();

    expect(collect($schedule->events())->map(fn (Event $event): string => $event->command . ' ' . $event->expression)->implode("\n"))
        ->toContain('model:prune')
        ->toContain('FormSubmission')
        ->toContain('0 0 * * *');
});

it('lets an editor pick the retention, and says it with the submissions', function () {
    test()->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));
    config(['filament-form-builder.retention_months' => 12]);
    $form = keptForm('Sollicitatie');

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertFormFieldExists('retention_months', fn (Select $field): bool => $field->getPlaceholder() === 'Default (12 months)' && $field->getOptions()[0] === 'Forever')
        ->fillForm(['retention_months' => 6])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($form->fresh()->retention_months)->toBe(6);

    Livewire::test(FormSubmissionsRelationManager::class, ['ownerRecord' => $form->fresh(), 'pageClass' => EditForm::class])
        ->assertSee('Submissions are deleted automatically after 6 months.');
});
