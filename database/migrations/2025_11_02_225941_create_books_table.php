<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('author', 20);
            $table->string('title', 20);
            $table->string('description', 255)->nullable();
            $table->integer('price')->default(0); 
            $table->integer('number_of_likes')->default(0);
            $table->float('discount_rate')->default(0);
            $table->enum('book_type', ['free', 'paid'])->default('free');
            $table->string('file_path')->nullable(); 
            $table->string('file_type')->default('pdf'); 
            $table->bigInteger('file_size')->nullable(); 
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};

