<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn([
                'notification_enabled',
                'notification_sender',
                'notification_receivers',
                'notification_subject',
                'notification_content',
            ]);

            $table->json('notifications')->nullable()->after('custom');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->boolean('notification_enabled')->default(false)->after('custom');
            $table->string('notification_sender')->nullable()->after('notification_enabled');
            $table->json('notification_receivers')->nullable()->after('notification_sender');
            $table->string('notification_subject')->nullable()->after('notification_receivers');
            $table->text('notification_content')->nullable()->after('notification_subject');
        });
    }
};
