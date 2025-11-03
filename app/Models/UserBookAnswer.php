<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBookAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'book_id', 'question_id',
        'answer_id', 'is_correct', 'completed'
    ];
}
