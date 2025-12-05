<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
       
        Schema::table('user_book_answers', function (Blueprint $table) {
          
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('user_book_answers');

            if (!array_key_exists('uba_user_book_question_unique', $indexes)) {
                $table->unique(['user_id', 'book_id', 'question_id'], 'uba_user_book_question_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_book_answers', function (Blueprint $table) {
            $table->dropUnique('uba_user_book_question_unique');
        });
    }
};
