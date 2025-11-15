<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_user', function (Blueprint $table) {
            // نضيف owned و downloaded_at إن لم يكونا موجودين
            if (!Schema::hasColumn('book_user', 'owned')) {
                $table->boolean('owned')->default(false)->after('liked');
            }

            if (!Schema::hasColumn('book_user', 'downloaded_at')) {
                $table->timestamp('downloaded_at')->nullable()->after('owned');
            }
        });
    }

    public function down(): void
    {
        Schema::table('book_user', function (Blueprint $table) {
            if (Schema::hasColumn('book_user', 'downloaded_at')) {
                $table->dropColumn('downloaded_at');
            }
            if (Schema::hasColumn('book_user', 'owned')) {
                $table->dropColumn('owned');
            }
        });
    }
};
