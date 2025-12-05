<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('reading_platform_users', function (Blueprint $table) {
            $table->id(); 
            $table->string('username', 20); 
            $table->integer('age'); 
            $table->string('email')->unique(); 
            $table->enum('gender', ['male', 'female']); 
            $table->string('password'); 
            $table->integer('user_type')->default(1); 
            $table->integer('points')->default(0); 
            $table->integer('purchases_count')->default(0); 
            $table->rememberToken(); 
            $table->timestamps();
        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('reading_platform_users');
    }
};