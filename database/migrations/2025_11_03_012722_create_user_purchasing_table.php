<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_purchasing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('reading_platform_users')->onDelete('cascade');
            $table->foreignId('purchasing_id')->constrained('purchasing')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_purchasing');
    }
};
