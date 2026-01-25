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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('articles')->cascadeOnDelete(); // Grouping
            $table->text('guid')->nullable();
            $table->string('title')->nullable();
            $table->text('original_url');
            $table->text('decoded_url')->nullable();
            $table->string('source_name')->nullable(); // Store "Habr", "yk24.ru" etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
