<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionUpdated;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\RelationManagers\FormSubmissionsRelationManager;
use VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource;
use VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource\Pages\ViewFormSubmission;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

beforeEach(function () {
    $this->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));
});

/**
 * @return list<FormSubmission> oldest first
 */
function inbox(int $count = 3): array
{
    $form = Form::create(['title' => 'Contact ' . uniqid(), 'template' => 'custom', 'custom' => ['fields' => [['type' => 'text', 'label' => 'Naam', 'key' => 'naam']]]]);

    return array_map(fn (int $i): FormSubmission => FormSubmission::create(['form_id' => $form->id, 'data' => ['naam' => "Jan {$i}"]]), range(1, $count));
}

it('marks a submission read once it is opened, without counting that as a change', function () {
    [$submission] = inbox(1);
    $updatedAt = $submission->updated_at;
    Event::fake([FormSubmissionUpdated::class]);
    $this->travel(1)->minute();

    expect($submission->isRead())->toBeFalse()
        ->and(FormSubmissionResource::getNavigationBadge())->toBe('1');

    Livewire::test(ViewFormSubmission::class, ['record' => $submission->getRouteKey()]);

    expect($submission->refresh()->isRead())->toBeTrue()
        ->and($submission->updated_at->equalTo($updatedAt))->toBeTrue()
        ->and(FormSubmissionResource::getNavigationBadge())->toBeNull();
    Event::assertNotDispatched(FormSubmissionUpdated::class);
});

it('marks a submission unread again and goes back to its list', function () {
    [$submission] = inbox(1);

    Livewire::test(ViewFormSubmission::class, ['record' => $submission->getRouteKey()])
        ->callAction('markUnread')
        ->assertRedirect(FormResource::getUrl('edit', ['record' => $submission->form_id, 'tab' => 'submissions']));

    expect($submission->refresh()->isRead())->toBeFalse();
});

it('steps to the newer and the older submission of the same form', function () {
    [$oldest, $middle, $newest] = inbox();
    inbox(1);
    $page = fn (FormSubmission $submission) => Livewire::test(ViewFormSubmission::class, ['record' => $submission->getRouteKey()]);
    $url = fn (FormSubmission $submission): string => FormSubmissionResource::getUrl('view', ['record' => $submission]);

    $page($middle)
        ->assertActionHasUrl('newer', $url($newest))
        ->assertActionHasUrl('older', $url($oldest));

    $page($newest)->assertActionDisabled('newer');
    $page($oldest)->assertActionDisabled('older');
});

it('filters and marks the submissions of a form by whether they were read', function () {
    [$read, $unread] = inbox(2);
    $read->markAsRead();
    $table = Livewire::test(FormSubmissionsRelationManager::class, ['ownerRecord' => $read->form, 'pageClass' => EditForm::class]);

    $table->filterTable('read_at', false)
        ->assertCanSeeTableRecords([$unread])
        ->assertCanNotSeeTableRecords([$read]);

    $table->selectTableRecords([$unread])->callAction(TestAction::make('mark_read')->table()->bulk());

    expect($unread->refresh()->isRead())->toBeTrue();
});
