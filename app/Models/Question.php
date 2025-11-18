<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    // بعد إزالة عمود 'answer' من الجدول، لم نعد نحتاجه في fillable
    protected $fillable = ['question_text', 'book_id'];

    // علاقة السؤال بالكتاب
    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    // علاقة السؤال بالإجابات (One to Many)
    public function answers()
    {
        return $this->hasMany(Answer::class);
    }
}
