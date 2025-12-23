<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id('notification_id');

            $table->string('title', 20);           // عنوان الإشعار
            $table->string('content', 255);        // محتوى الإشعار

            // الاداري الذي أنشأ الإشعار
            $table->unsignedBigInteger('created_by');

            $table->timestamps();

            // ربط مع جدول users
            $table->foreign('created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade'); // إذا حذف الاداري يحذف إشعاراته
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
