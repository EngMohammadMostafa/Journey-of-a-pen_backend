<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reading_platform_users', function (Blueprint $table) {
            $table->id(); // ID تلقائي
            $table->string('username', 20); // اسم المستخدم
            $table->integer('age'); // العمر
            $table->string('email')->unique(); // البريد الإلكتروني (فريد)
            $table->enum('gender', ['male', 'female']); // الجنس (ذكر أو أنثى فقط)
            $table->string('password'); // كلمة المرور
            $table->integer('user_type')->default(1); // 1 = user, 2 = admin
            $table->integer('points')->default(0); // النقاط
            $table->integer('purchases_count')->default(0); // عدد المشتريات
            $table->rememberToken(); // لحفظ حالة تسجيل الدخول
            $table->timestamps(); // تاريخ الإنشاء والتحديث
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_platform_users');
    }
};