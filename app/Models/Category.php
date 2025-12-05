<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

   
    protected $fillable = ['name'];

    
    public function books()
    {
        return $this->hasMany(Book::class);
    }

    
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($category) {
            
            $category->books()->chunkById(50, function ($books) {
                foreach ($books as $book) {
                    $book->delete(); 
                }
            });
        });
    }
}
