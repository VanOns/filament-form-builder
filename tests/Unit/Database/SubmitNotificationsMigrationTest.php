<?php

use Illuminate\Support\Facades\DB;
use VanOns\FilamentFormBuilder\Models\Form;

it('moves what a form did after a submission into the list, and back', function () {
    $migration = require __DIR__ . '/../../../database/migrations/2026_10_07_120000_merge_submit_notification_columns_into_a_list_on_forms_table.php';
    $migration->down();

    $old = fn (string $title, array $columns): bool => DB::table('forms')->insert(['title' => $title, 'template' => 'custom', 'created_at' => now(), 'updated_at' => now(), ...$columns]);
    $old('Bericht', ['submit_notification_type' => 'content', 'submit_notification_content' => '<p>Bedankt!</p>']);
    $old('Pagina', ['submit_notification_type' => 'url', 'submit_notification_url' => '{"page":3}', 'submit_notification_query' => 'naam={{ $naam }}']);

    $migration->up();

    expect(Form::firstWhere('title', 'Bericht')->getSubmitNotifications())->sequence(
        fn ($outcome) => $outcome->toMatchArray(['type' => 'content', 'content' => '<p>Bedankt!</p>', 'conditions' => [], 'url' => null]),
    )
        ->and(Form::firstWhere('title', 'Pagina')->getSubmitNotifications()[0])
        ->toMatchArray(['type' => 'url', 'url' => ['page' => 3], 'query' => 'naam={{ $naam }}']);

    $migration->down();

    expect(DB::table('forms')->where('title', 'Pagina')->first())
        ->submit_notification_type->toBe('url')
        ->submit_notification_url->toBe('{"page":3}')
        ->submit_notification_query->toBe('naam={{ $naam }}');

    $migration->up();
});
