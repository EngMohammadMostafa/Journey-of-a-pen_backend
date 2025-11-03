<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puy', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->timestamp('date')->useCurrent();
            $table->foreignId('user_id')->constrained('reading_platform_users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puy');
    }
};
