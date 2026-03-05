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
        Schema::create('saved_articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('article_id')->nullable(); // Unique identifier per source
            $table->string('headline');
            $table->text('summary')->nullable();
            $table->string('source')->nullable();
            $table->text('url')->nullable();
            $table->text('image')->nullable();
            $table->string('category')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // Unique constraint to prevent saving the same article URL multiple times per user
            // If user_id is null (guest mode), we might use session or IP, but let's just make url unique for now or unique together with user_id
            $table->unique(['user_id', 'url']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_articles');
    }
};
