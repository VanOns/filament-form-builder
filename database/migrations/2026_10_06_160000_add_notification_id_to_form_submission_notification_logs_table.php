<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('form_submission_notification_logs', function (Blueprint $table) {
            $table->string('notification_id')->nullable()->after('form_submission_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('form_submission_notification_logs', function (Blueprint $table) {
            $table->dropColumn('notification_id');
        });
    }
};
