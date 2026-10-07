<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

it('brings the tables of a v2 install to the v3 schema, and back', function () {
    $migration = require __DIR__ . '/../../../database/upgrades/2026_10_08_000000_upgrade_filament_form_builder_tables_to_v3.php';
    $columns = fn (): array => [
        'forms' => Schema::getColumnListing('forms'),
        'form_submissions' => Schema::getColumnListing('form_submissions'),
        'form_submission_notification_logs' => Schema::getColumnListing('form_submission_notification_logs'),
    ];
    $v3 = $columns();

    $migration->down();

    expect(Schema::hasColumns('forms', ['submit_notification_type', 'submit_notification_url', 'submit_notification_query']))->toBeTrue()
        ->and(Schema::hasColumn('form_submissions', 'submitter_email'))->toBeTrue()
        ->and(Schema::hasColumn('form_submissions', 'read_at'))->toBeFalse();

    $old = fn (string $title, array $columns): int => DB::table('forms')->insertGetId(['title' => $title, 'template' => 'custom', 'created_at' => now(), 'updated_at' => now(), ...$columns]);
    $old('Bericht', ['submit_notification_type' => 'content', 'submit_notification_content' => '<p>Bedankt!</p>']);
    $page = $old('Pagina', ['submit_notification_type' => 'url', 'submit_notification_url' => '{"page":3}', 'submit_notification_query' => 'naam={{ $naam }}']);
    DB::table('form_submissions')->insert(['form_id' => $page, 'submitter_email' => 'jan@example.com', 'data' => '{"naam":"Jan"}', 'created_at' => '2026-01-02 03:04:05', 'updated_at' => now()]);

    $migration->up();

    expect($columns())->toEqualCanonicalizing($v3)
        ->and(Form::firstWhere('title', 'Bericht')->getSubmitNotifications())->sequence(
            fn ($outcome) => $outcome->toMatchArray(['type' => 'content', 'content' => '<p>Bedankt!</p>', 'conditions' => [], 'url' => null]),
        )
        ->and(Form::firstWhere('title', 'Pagina')->getSubmitNotifications()[0])
        ->toMatchArray(['type' => 'url', 'url' => ['page' => 3], 'query' => 'naam={{ $naam }}'])
        ->and(FormSubmission::sole()->read_at?->toDateTimeString())->toBe('2026-01-02 03:04:05');

    $migration->down();

    expect(DB::table('forms')->where('title', 'Pagina')->first())
        ->submit_notification_type->toBe('url')
        ->submit_notification_url->toBe('{"page":3}')
        ->submit_notification_query->toBe('naam={{ $naam }}');

    $migration->up();
});
