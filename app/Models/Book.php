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

    /**
     * العلاقة مع المستخدمين — نُعيد pivot fields أيضاً
     */
    public function users()
    {
        return $this->belongsToMany(ReadingPlatformUser::class, 'book_user')
                    ->withPivot('liked', 'owned', 'downloaded_at')
                    ->withTimestamps();
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}
