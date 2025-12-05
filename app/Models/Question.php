<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

  
    
    protected $fillable = ['question_text', 'book_id'];
    
    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    
    public function answers()
    {
        return $this->hasMany(Answer::class);
    }
}
