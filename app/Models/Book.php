<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'author', 'title', 'description', 'price',
        'number_of_likes', 'discount_rate', 'book_type',
        'file_path', 'file_type', 'file_size', 'category_id'
    ];

    // 🔗 العلاقات
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function users()
    {
        return $this->belongsToMany(ReadingPlatformUser::class, 'book_user')
                    ->withTimestamps();
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}
