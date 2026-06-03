<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->text('submit_notification_url')->nullable()->after('submit_notification_content');
        });

        // Backward compatibility: existing url-type forms stored the redirect in
        // submit_notification_content. Move it to the new dedicated column.
        DB::statement("update forms set submit_notification_url = submit_notification_content, submit_notification_content = null where submit_notification_type = 'url'");
    }

    public function down(): void
    {
        DB::statement("update forms set submit_notification_content = submit_notification_url where submit_notification_type = 'url' and submit_notification_url is not null");

        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('submit_notification_url');
        });
    }
};
