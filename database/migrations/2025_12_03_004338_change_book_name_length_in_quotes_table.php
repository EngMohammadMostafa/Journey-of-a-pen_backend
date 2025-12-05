<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('book_name', 50)->change();
        });
    }

    
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('book_name', 20)->change();
        });
    }
};
