<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_books', function (Blueprint $table) {
            $table->id('request_id');
            $table->unsignedBigInteger('user_id'); // FK للمستخدم
            $table->string('title', 50);
            $table->string('author', 30);
            $table->string('description', 255);
            $table->integer('price')->nullable(); // يمكن أن يكون مجاني
            $table->enum('book_type', ['free', 'paid']);
            $table->string('file_path', 255);
            $table->string('file_type', 20);
            $table->bigInteger('file_size');
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();

            // Foreign key للمستخدم
            $table->foreign('user_id')
                  ->references('id')
                  ->on('reading_platform_users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_books');
    }
};
