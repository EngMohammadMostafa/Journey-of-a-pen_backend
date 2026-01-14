<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::table('request_books', function (Blueprint $table) {
            
            if (Schema::hasColumn('request_books', 'author')) {
                $table->dropColumn('author');
            }
        });
    }

    
    public function down(): void
    {
        Schema::table('request_books', function (Blueprint $table) {
           
            $table->string('author', 30)->after('title');
        });
    }
};
