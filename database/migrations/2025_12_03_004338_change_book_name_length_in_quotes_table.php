<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تغيير طول عمود book_name إلى 50
     */
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('book_name', 50)->change();
        });
    }

    /**
     * إعادة طول العمود إلى 20 في حال التراجع
     */
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('book_name', 20)->change();
        });
    }
};
