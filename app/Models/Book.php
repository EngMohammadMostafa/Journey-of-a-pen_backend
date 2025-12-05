<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Book extends Model
{
    use HasFactory;

    
    protected $fillable = [
        'author', 'title', 'description', 'price',
        'number_of_likes', 'book_type',
        'file_path', 'file_type', 'file_size', 'category_id'
    ];

    
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

   
    public function users()
    {
        return $this->belongsToMany(
            ReadingPlatformUser::class,
            'book_user',
            'book_id',   
            'user_id'   
        )
        ->withPivot('liked', 'owned', 'downloaded_at')
        ->withTimestamps();
    }

   
    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    
    public function likesCount()
    {
       
        return $this->users()->wherePivot('liked', true)->count();
    }

    
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($book) {
           
            foreach ($book->questions as $question) {
                $question->answers()->delete(); 
                $question->delete();           
            }

           
            if ($book->file_path && Storage::disk('local')->exists($book->file_path)) {
                Storage::disk('local')->delete($book->file_path);
            }

            
        });
    }
}
