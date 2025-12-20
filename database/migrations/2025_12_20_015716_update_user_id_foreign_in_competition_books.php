<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_books', function (Blueprint $table) {
            // حذف المفتاح الأجنبي القديم
            $table->dropForeign(['user_id']);

            // إنشاء مفتاح أجنبي جديد يشير إلى جدول reading_platform_users
            $table->foreign('user_id')
                  ->references('id')
                  ->on('reading_platform_users')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('competition_books', function (Blueprint $table) {
            // حذف المفتاح الجديد
            $table->dropForeign(['user_id']);

            // إعادة المفتاح القديم إلى جدول users
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->cascadeOnDelete();
        });
    }
};
