<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->string('title')->unique();
            $table->string('template')->nullable();
            $table->boolean('notification_enabled')->default(false);
            $table->string('notification_sender')->nullable();
            $table->json('notification_receivers')->nullable();
            $table->string('notification_subject')->nullable();
            $table->text('notification_content')->nullable();
            $table->string('submit_notification_type')->nullable();
            $table->text('submit_notification_content')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
