<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id('notification_id');
            $table->string('title', 20);
            $table->string('content', 255);

           
            $table->unsignedBigInteger('user_id');

            $table->timestamps();

            $table->foreign('user_id')
                  ->references('id')
                  ->on('reading_platform_users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
