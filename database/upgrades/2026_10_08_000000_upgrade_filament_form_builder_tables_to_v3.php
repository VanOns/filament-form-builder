<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Brings the tables of a v2.9 install to the v3 schema; a new install gets that
 * schema from the create migrations and never publishes this one.
 */
return new class () extends Migration {
    private const SUBMIT_NOTIFICATION_COLUMNS = ['submit_notification_type', 'submit_notification_content', 'submit_notification_url', 'submit_notification_query'];

    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropColumn('submitter_email');
            $table->json('files')->nullable()->after('data');
            $table->json('field_snapshot')->nullable()->after('files');
            $table->text('source_url')->nullable()->after('field_snapshot');
            $table->timestamp('read_at')->nullable()->after('integrations')->index();
        });

        // What came in before is not news to anyone, so it does not start out unread.
        DB::table('form_submissions')->update(['read_at' => DB::raw('created_at')]);

        Schema::table('form_submission_notification_logs', function (Blueprint $table) {
            $table->string('notification_id')->nullable()->after('form_submission_id')->index();
        });

        $this->moveSubmitNotificationsIntoList();
    }

    public function down(): void
    {
        $this->moveSubmitNotificationsOutOfList();

        Schema::table('form_submission_notification_logs', function (Blueprint $table) {
            $table->dropIndex(['notification_id']);
            $table->dropColumn('notification_id');
        });

        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropIndex(['read_at']);
            $table->dropColumn(['files', 'field_snapshot', 'source_url', 'read_at']);
            $table->string('submitter_email')->nullable()->after('form_id');
        });
    }

    /**
     * What happens after a submission becomes a list, like the notifications: the
     * outcome a form had is its last item, the one without conditions.
     */
    private function moveSubmitNotificationsIntoList(): void
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
            $table->dropColumn(self::SUBMIT_NOTIFICATION_COLUMNS);
        });
    }

    private function moveSubmitNotificationsOutOfList(): void
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
