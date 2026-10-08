<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // A v2 install got it from the upgrade migration, before publishing this one.
        if (Schema::hasTable('form_submission_integration_logs')) {
            return;
        }

        Schema::create('form_submission_integration_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_submission_id')->constrained('form_submissions')->cascadeOnDelete();
            $table->string('integration_id')->index();
            $table->string('integration');
            $table->string('status')->default('queued');
            $table->json('response')->nullable();
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('ran_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submission_integration_logs');
    }
};
