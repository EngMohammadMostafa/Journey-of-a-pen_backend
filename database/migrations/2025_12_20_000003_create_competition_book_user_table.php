<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        Schema::create('competition_book_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('competition_book_id');
            $table->foreign('competition_book_id')
                  ->references('competition_book_id')
                  ->on('competition_books')
                  ->cascadeOnDelete();
        
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
        
            $table->boolean('liked')->default(true);
        
            $table->unique(['competition_book_id', 'user_id']);
        
            $table->timestamps();
        });
        
    }

   
    public function down(): void
    {
        Schema::dropIfExists('competition_book_user');
    }
};
