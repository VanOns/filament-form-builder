<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * What happens after a submission becomes a list, like the notifications: the
 * outcome a form had is its last item, the one without conditions.
 */
return new class () extends Migration {
    private const COLUMNS = ['submit_notification_type', 'submit_notification_content', 'submit_notification_url', 'submit_notification_query'];

    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->json('submit_notifications')->nullable()->after('notifications');
        });

        DB::table('forms')->orderBy('id')->each(function (object $form): void {
            $url = $form->submit_notification_url;

            // A structured URL, such as a page picked in the CMS, was stored as JSON.
            if (is_string($url) && in_array($url[0] ?? '', ['{', '['], true) && is_array($decoded = json_decode($url, true))) {
                $url = $decoded;
            }

            DB::table('forms')->where('id', $form->id)->update(['submit_notifications' => json_encode([[
                'id' => (string) Str::uuid(),
                'conditions' => [],
                'conditionMatch' => 'all',
                'type' => $form->submit_notification_type ?: 'content',
                'content' => $form->submit_notification_content,
                'url' => $url,
                'query' => $form->submit_notification_query,
            ]])]);
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNS);
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->string('submit_notification_type')->nullable();
            $table->text('submit_notification_content')->nullable();
            $table->text('submit_notification_url')->nullable();
            $table->text('submit_notification_query')->nullable();
        });

        DB::table('forms')->orderBy('id')->each(function (object $form): void {
            $outcomes = json_decode((string) $form->submit_notifications, true);
            $outcome = is_array($outcomes) && $outcomes !== [] ? end($outcomes) : [];

            DB::table('forms')->where('id', $form->id)->update([
                'submit_notification_type' => $outcome['type'] ?? null,
                'submit_notification_content' => $outcome['content'] ?? null,
                'submit_notification_url' => is_array($outcome['url'] ?? null) ? json_encode($outcome['url']) : ($outcome['url'] ?? null),
                'submit_notification_query' => $outcome['query'] ?? null,
            ]);
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('submit_notifications');
        });
    }
};
