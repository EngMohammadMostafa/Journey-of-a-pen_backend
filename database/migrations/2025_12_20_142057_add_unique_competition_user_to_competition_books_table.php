<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_books', function (Blueprint $table) {
            $table->unique(
                ['competition_id', 'user_id'],
                'competition_books_competition_user_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('competition_books', function (Blueprint $table) {
            $table->dropUnique('competition_books_competition_user_unique');
        });
    }
};
