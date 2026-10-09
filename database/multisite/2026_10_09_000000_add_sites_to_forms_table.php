<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * For forms per site with filament-multisite; published on its own, as only
 * an app that turns `multisite` on needs it.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->string('site')->default('default')->after('id')->index();
            // A form whose origin is deleted for good stays, as an origin of its own.
            $table->foreignId('origin_id')->nullable()->after('site')->constrained('forms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origin_id');
            $table->dropIndex(['site']);
            $table->dropColumn('site');
        });
    }
};
