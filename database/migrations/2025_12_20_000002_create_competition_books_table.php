<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_books', function (Blueprint $table) {
            $table->bigIncrements('competition_book_id'); // PK + auto increment

            $table->foreignId('competition_id')
                  ->constrained('competitions')
                  ->cascadeOnDelete();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->string('title', 50);
            $table->string('file_path', 255);
            $table->string('file_type', 20);
            $table->bigInteger('file_size'); 
            $table->integer('likes_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_books');
    }
};
