<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\ConsentField;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use VanOns\FilamentFormBuilder\Models\Form;

it('keeps only the links and emphasis of the consent text, and opens its links in a new tab', function () {
    $field = new ConsentField(['key' => 'akkoord', 'text' => '<p>Ik ga akkoord met de <a href="https://example.test/privacy">privacyverklaring</a> &amp; <strong>voorwaarden</strong></p><p onclick="x()"><a href="javascript:alert(1)">hier</a><script>alert(1)</script></p>']);

    expect($field->getTextHtml()->toHtml())
        ->toBe('Ik ga akkoord met de <a href="https://example.test/privacy" target="_blank" rel="noopener">privacyverklaring</a> &amp; <strong>voorwaarden</strong> <a target="_blank" rel="noopener">hier</a>')
        ->and($field->getLabel())->toBe('Ik ga akkoord met de privacyverklaring & voorwaarden hier');
});

it('reads the consent text while the editor still holds it as a document', function () {
    $field = new ConsentField(['text' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'Ik ga akkoord met de '],
        ['type' => 'text', 'text' => 'privacyverklaring', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.test/privacy']]]],
    ]]]]]);

    expect($field->getTextHtml()->toHtml())->toBe('Ik ga akkoord met de <a href="https://example.test/privacy" target="_blank" rel="noopener">privacyverklaring</a>')
        ->and($field->getLabel())->toBe('Ik ga akkoord met de privacyverklaring')
        ->and((new ConsentField(['text' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]]]))->getLabel())->toBe('Consent');
});

it('goes by its own name in overviews when it has one', function () {
    expect((new ConsentField(['text' => '<p>Ik ga akkoord</p>', 'label' => 'Privacy']))->getLabel())->toBe('Privacy')
        ->and((new ConsentField())->getLabel())->toBe('Consent')
        ->and((new ConsentField())->getTextHtml()->toHtml())->toBe('Consent');
});

it('always asks for consent, ticked by the visitor', function () {
    view()->share('errors', new ViewErrorBag());
    $form = Form::create(['title' => 'Nieuwsbrief', 'template' => 'custom', 'custom' => ['fields' => [
        ['type' => 'consent', 'text' => '<p>Ik ga akkoord met de <a href="https://example.test/privacy">privacyverklaring</a></p>', 'key' => 'akkoord', 'required' => false, 'defaultValue' => true],
    ]]]);
    $field = $form->getFields(inputsOnly: true)[0];

    expect($field->isRequired())->toBeTrue()
        ->and($field->getRules()['akkoord'])->toContain('required')
        ->and((string) $field->render())->toContain('<a href="https://example.test/privacy"')
        ->toContain(' required')
        ->not->toContain('checked');
});

it('shows a consent on the canvas with its link', function () {
    test()->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));

    $form = Form::create(['title' => 'Inschrijven', 'template' => 'custom', 'custom' => ['fields' => [
        ['type' => 'consent', 'text' => '<p>Ik ga akkoord met de <a href="https://example.test/privacy">privacyverklaring</a></p>', 'label' => 'Privacy', 'key' => 'akkoord'],
    ]]]);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertSeeHtml('<a href="https://example.test/privacy" target="_blank" rel="noopener">privacyverklaring</a>');
});
