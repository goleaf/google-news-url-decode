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
        Schema::create('article_related', function (Blueprint $table): void {
            $table->foreignId('parent_id')->constrained('articles')->onDelete('cascade');
            $table->foreignId('related_id')->constrained('articles')->onDelete('cascade');
            $table->primary(['parent_id', 'related_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_related');
    }
};
