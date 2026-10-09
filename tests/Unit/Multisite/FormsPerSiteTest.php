<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Fixtures\PickFormPage;
use VanOns\FilamentFormBuilder\Filament\Forms\Components\FormSelect;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\ListForms;
use VanOns\FilamentFormBuilder\Models\Form;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag());
    config([
        'filament-multisite.sites' => [
            'default' => ['name' => 'Nederlands', 'short_name' => 'NL', 'locale' => 'nl_NL', 'url' => '/'],
            'en' => ['name' => 'English', 'short_name' => 'EN', 'locale' => 'en_US', 'url' => '/en'],
        ],
    ]);
});

function migrateSites(): void
{
    (require __DIR__ . '/../../../database/multisite/2026_10_09_000000_add_sites_to_forms_table.php')->up();

    // Eloquent keeps the columns it may fill per process; an earlier test saw the table without these.
    (new ReflectionProperty(Model::class, 'guardableColumns'))->setValue(null, []);
}

function sitedForm(string $title = 'Contact'): Form
{
    return Form::create([
        'title' => $title . ' ' . Str::random(4),
        'template' => 'custom',
        'custom' => ['fields' => [['type' => 'text', 'label' => 'Naam', 'key' => 'naam']]],
        'submit_notifications' => [['type' => 'content', 'content' => '<p>Bedankt!</p>']],
    ]);
}

function formSelect(Testable $page): FormSelect
{
    return collect($page->instance()->form->getFlatComponents())->first(fn ($component): bool => $component instanceof FormSelect);
}

function onPage(string $url): void
{
    app()->instance('request', Request::create($url));
}

it('leaves forms alone until multisite is turned on, also without its columns', function () {
    $form = sitedForm();
    $form->update(['retention_months' => 6]);

    onPage('http://localhost/en/contact');

    expect(Form::usesSites())->toBeFalse()
        ->and($form->inCurrentSite()->is($form))->toBeTrue()
        ->and(Form::idInSite($form->id, 'en'))->toBe($form->id);
});

it('makes a copy for another site under its own title, linked to its origin', function () {
    config(['filament-form-builder.multisite' => true]);
    migrateSites();
    $nl = sitedForm();

    $en = $nl->findOrCreateInSite('en');

    expect($en)
        ->site->toBe('en')
        ->origin_id->toBe($nl->id)
        ->title->toBe("{$nl->title} (EN)")
        ->custom->toBe($nl->custom)
        ->and($nl->findOrCreateInSite('en')->is($en))->toBeTrue();

    $en->update(['title' => 'Contact us', 'custom' => ['fields' => [['type' => 'text', 'label' => 'Name', 'key' => 'naam']]]]);
    $nl->update(['retention_months' => 12, 'custom' => ['fields' => []]]);

    expect($en->refresh())
        ->retention_months->toBe(12)
        ->custom->toBe(['fields' => [['type' => 'text', 'label' => 'Name', 'key' => 'naam']]])
        ->and($nl->refresh()->title)->not->toBe('Contact us');
});

it('shows the form of the site a page is on, or the one it has while that site has none', function () {
    config(['filament-form-builder.multisite' => true]);
    migrateSites();
    $nl = sitedForm();
    $other = sitedForm('Nieuwsbrief');
    $en = $nl->findOrCreateInSite('en');
    $render = fn (Form $form): string => Blade::render('<x-render-form :form="$form" />', ['form' => $form]);

    onPage('http://localhost/en/contact');

    expect($render($nl))->toContain("/filament-form-builder/{$en->id}/submit")
        ->and($render($other))->toContain("/filament-form-builder/{$other->id}/submit")
        ->and(Form::idInSite($nl->id, 'en'))->toBe($en->id)
        ->and(Form::idInSite($en->id, 'default'))->toBe($nl->id)
        ->and(Form::idInSite($other->id, 'en'))->toBe($other->id);

    onPage('http://localhost/contact');

    expect($render($en))->toContain("/filament-form-builder/{$nl->id}/submit");
});

it('offers the sites in the panel and keeps what follows the origin from changing on a copy', function () {
    config(['filament-form-builder.multisite' => true]);
    migrateSites();
    $this->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));
    $nl = sitedForm();
    $en = $nl->findOrCreateInSite('en');

    Livewire::test(ListForms::class)
        ->assertTableColumnExists('site')
        ->assertTableFilterExists('site')
        ->filterTable('site', 'en')
        ->assertCanSeeTableRecords([$en])
        ->assertCanNotSeeTableRecords([$nl]);

    Livewire::test(EditForm::class, ['record' => $nl->getRouteKey()])
        ->assertSee(['Nederlands', 'English'])
        ->assertFormFieldIsEnabled('retention_months');

    Livewire::test(EditForm::class, ['record' => $en->getRouteKey()])
        ->assertFormFieldIsDisabled('retention_months')
        ->assertFormFieldIsEnabled('title');
});

it('picks the forms of the site a page is on, also for a page copied from another site', function () {
    config(['filament-form-builder.multisite' => true]);
    migrateSites();
    $nl = sitedForm();
    $other = sitedForm('Nieuwsbrief');
    $en = $nl->findOrCreateInSite('en');
    $page = $other->findOrCreateInSite('en');

    $copied = Livewire::test(PickFormPage::class, ['record' => $page, 'picked' => $nl->id])
        ->assertSet('data.form_id', $en->id);

    expect(array_keys(formSelect($copied)->getFormOptions()))->toEqualCanonicalizing([$en->id, $page->id]);

    Livewire::test(PickFormPage::class, ['record' => $nl, 'picked' => $en->id])
        ->assertSet('data.form_id', $nl->id);
});

it('picks any form without forms per site', function () {
    $nl = sitedForm();
    $other = sitedForm('Nieuwsbrief');

    $page = Livewire::test(PickFormPage::class, ['record' => $other, 'picked' => $nl->id])
        ->assertSet('data.form_id', $nl->id);

    expect(array_keys(formSelect($page)->getFormOptions()))->toEqualCanonicalizing([$nl->id, $other->id]);
});

it('says how to add the columns when multisite is on without them', function () {
    config(['filament-form-builder.multisite' => true]);
    $this->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));

    Livewire::test(ListForms::class);
})->throws(Exception::class, 'filament-form-builder-multisite-migrations');
