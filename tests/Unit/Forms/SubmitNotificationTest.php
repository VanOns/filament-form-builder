<?php

use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\QueryParameters;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

function thankingForm(array $attributes = []): Form
{
    return Form::create([
        'title' => 'Solliciteren ' . uniqid(),
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
            ['type' => 'email', 'label' => 'E-mailadres', 'key' => 'email'],
        ]],
        'submit_notification_type' => 'content',
        'submit_notification_content' => '<p>Bedankt!</p>',
        ...$attributes,
    ]);
}

function thankYouFor(Form $form): ?string
{
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['naam' => 'Jan', 'email' => 'jan@example.test']]);

    return $form->getType()->resolveSubmitNotification($submission)->getNotificationMessage();
}

it('fills in the tags of the thank-you message, but never the link to the panel', function () {
    $message = thankYouFor(thankingForm(['submit_notification_content' => '<p>Bedankt <span data-type="mergeTag" data-id="naam"></span>!</p>'
        . '<p><span data-type="mergeTag" data-id="all_fields"></span></p><p><span data-type="mergeTag" data-id="submission_url"></span></p>']));

    expect($message)->toContain('Bedankt Jan!')
        ->toContain('<b>E-mailadres</b>: jan@example.test')
        ->not->toContain('/admin');
});

it('shows no message when nothing is left of it', function () {
    expect(thankYouFor(thankingForm(['submit_notification_content' => '<p><span data-type="mergeTag" data-id="weg"></span></p>'])))->toBeNull();
});

it('reads a query string of parameters as rows and writes them back the same', function () {
    $query = 'naam={{ $naam }}&form={{ $form_title }}';

    expect(QueryParameters::parse($query))->toBe([['name' => 'naam', 'value' => 'naam'], ['name' => 'form', 'value' => 'form_title']])
        ->and(QueryParameters::build(QueryParameters::parse($query)))->toBe($query)
        ->and(QueryParameters::build([]))->toBeNull()
        ->and(QueryParameters::parse('?naam={{$naam}}&'))->toBe([['name' => 'naam', 'value' => 'naam']])
        ->and(QueryParameters::parse('utm=site&naam={{ $naam }}'))->toBeNull()
        ->and(QueryParameters::parse('naam={{ $voornaam }}-{{ $achternaam }}'))->toBeNull();
});

it('edits the parameters as rows and keeps a query string with more than that as text', function () {
    test()->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));

    $redirecting = ['submit_notification_type' => 'url', 'submit_notification_url' => 'https://example.test/bedankt'];
    $rows = thankingForm([...$redirecting, 'submit_notification_query' => 'naam={{ $naam }}']);
    $text = thankingForm([...$redirecting, 'submit_notification_query' => 'utm=site&naam={{ $naam }}']);

    Livewire::test(EditForm::class, ['record' => $rows->getRouteKey()])
        ->fillForm(['submit_notification_query' => [['name' => 'naam', 'value' => 'naam'], ['name' => 'form', 'value' => 'form_title']]])
        ->call('save')
        ->assertHasNoFormErrors();

    Livewire::test(EditForm::class, ['record' => $text->getRouteKey()])
        ->assertSchemaStateSet(['submit_notification_query' => 'utm=site&naam={{ $naam }}'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($rows->refresh()->submit_notification_query)->toBe('naam={{ $naam }}&form={{ $form_title }}')
        ->and($text->refresh()->submit_notification_query)->toBe('utm=site&naam={{ $naam }}');
});

it('refuses a parameter name that would break the URL', function () {
    test()->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));

    $form = thankingForm(['submit_notification_type' => 'url', 'submit_notification_url' => 'https://example.test/bedankt']);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->fillForm(['submit_notification_query' => [['name' => 'naam&admin=1', 'value' => 'naam']]])
        ->call('save')
        ->assertHasFormErrors(['submit_notification_query.0.name' => 'regex']);
});
