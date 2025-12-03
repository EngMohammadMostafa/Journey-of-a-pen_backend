<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تعديل طول الأعمدة title و author
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('title', 50)->change();   // تعديل طول العنوان إلى 50 حرف
            $table->string('author', 30)->change();  // تعديل طول اسم الكاتب إلى 30 حرف
        });
    }

    /**
     * إعادة الأعمدة إلى القيم القديمة إذا تم التراجع
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('title', 20)->change();   // القيمة القديمة
            $table->string('author', 20)->change();  // القيمة القديمة
        });
    }
};
