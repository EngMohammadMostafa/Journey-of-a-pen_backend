<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('title', 50)->change();   
            $table->string('author', 30)->change();  
        });
    }

   
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('title', 20)->change();   
            $table->string('author', 20)->change();  
        });
    }
};
