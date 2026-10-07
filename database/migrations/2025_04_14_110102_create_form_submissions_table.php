<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')
                ->constrained('forms')
                ->cascadeOnDelete();
            $table->json('data')->nullable();
            $table->json('files')->nullable();
            $table->json('field_snapshot')->nullable();
            $table->json('integrations')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
