<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_user', function (Blueprint $table) {
            // تعديل قيمة افتراضية للعمود liked لتصبح false
            $table->boolean('liked')->default(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('book_user', function (Blueprint $table) {
            // إعادة القيمة الافتراضية كما كانت (true)
            $table->boolean('liked')->default(true)->change();
        });
    }
};
