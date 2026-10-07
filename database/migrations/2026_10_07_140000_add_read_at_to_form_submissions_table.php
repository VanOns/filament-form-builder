<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('integrations')->index();
        });

        // What came in before is not news to anyone, so it does not start out unread.
        DB::table('form_submissions')->update(['read_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropIndex(['read_at']);
            $table->dropColumn('read_at');
        });
    }
};
