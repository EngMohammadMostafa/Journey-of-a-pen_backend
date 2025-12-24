<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // إضافة FK جديد فقط على user_id
            $table->foreign('user_id')
                  ->references('id')
                  ->on('reading_platform_users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // إزالة FK عند الرجوع
            $table->dropForeign(['user_id']);
        });
    }
};
