<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_book_user', function (Blueprint $table) {
            $table->dropForeign(['user_id']);

            $table->foreign('user_id')
                  ->references('id')
                  ->on('reading_platform_users')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('competition_book_user', function (Blueprint $table) {
            $table->dropForeign(['user_id']);

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->cascadeOnDelete();
        });
    }
};
