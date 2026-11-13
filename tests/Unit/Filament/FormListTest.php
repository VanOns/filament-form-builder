<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\ListForms;
use VanOns\FilamentFormBuilder\Filament\Tables\SubmissionActivity;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

beforeEach(function () {
    $this->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));
});

function listedForm(string $title, int $submissions, array $attributes = []): Form
{
    $form = Form::create(['title' => $title, 'template' => 'custom', ...$attributes]);

    foreach (range(1, $submissions) as $_) {
        $submission = FormSubmission::create(['form_id' => $form->id, 'data' => []]);
        $submission->markAsRead();
    }

    return $form;
}

it('counts the submissions of the last weeks per week, this week last', function () {
    $form = Form::create(['title' => 'Contact', 'template' => 'custom']);
    $at = fn (Carbon $date) => FormSubmission::create(['form_id' => $form->id, 'data' => []])->forceFill(['created_at' => $date])->save();

    $at(now());
    $at(now());
    $at(now()->startOfWeek()->subWeek());
    $at(now()->subWeeks(20));

    expect(SubmissionActivity::weeksOf($form->id))->toBe([0, 0, 0, 0, 0, 0, 1, 2])
        ->and(SubmissionActivity::weeksOf(999))->toBe([0, 0, 0, 0, 0, 0, 0, 0]);
});

it('shows per form how many came in, how many are new and what follows a submission', function () {
    $busy = listedForm('Druk', 3, ['notifications' => [['to' => ['a@example.test']], ['to' => ['b@example.test'], 'enabled' => false]]]);
    $quiet = listedForm('Rustig', 1);
    FormSubmission::create(['form_id' => $busy->id, 'data' => []]);

    Livewire::test(ListForms::class)
        ->assertSee('1 new')
        ->assertSeeHtml('title="1 e-mail"')
        ->sortTable('submissions_count', 'desc')
        ->assertCanSeeTableRecords([$busy, $quiet], inOrder: true)
        ->sortTable('submissions_max_created_at')
        ->assertCanSeeTableRecords([$busy, $quiet]);
});
