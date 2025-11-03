<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repoint', function (Blueprint $table) {
            $table->id();
            $table->integer('number_of_points');
            $table->timestamp('repoint_date')->useCurrent();
            $table->foreignId('reward_id')->constrained('reward')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repoint');
    }
};
