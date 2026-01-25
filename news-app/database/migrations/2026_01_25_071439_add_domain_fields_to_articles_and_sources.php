<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->string('domain')->nullable()->after('url');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->string('source_domain')->nullable()->after('source_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn('domain');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('source_domain');
        });
    }
};
