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
        Schema::create('competition_books', function (Blueprint $table) {

            $table->id('competition_book_id');

            $table->foreignId('competition_id')
                  ->constrained('competitions')
                  ->cascadeOnDelete();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->string('title', 50);

            $table->string('file_path', 255);
            $table->string('file_type', 20);
            $table->bigInteger('file_size', 20);

            $table->integer('likes_count')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competition_books');
    }
};
