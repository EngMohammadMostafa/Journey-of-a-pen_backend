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
        Schema::table('request_books', function (Blueprint $table) {
            // حذف عمود author
            if (Schema::hasColumn('request_books', 'author')) {
                $table->dropColumn('author');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_books', function (Blueprint $table) {
            // إعادة إنشاء عمود author في حال التراجع عن الميغريشن
            $table->string('author', 30)->after('title');
        });
    }
};
