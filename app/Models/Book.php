<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Book extends Model
{
    use HasFactory;

    // الحقول التي يمكن تعبئتها بشكل جماعي
    protected $fillable = [
        'author', 'title', 'description', 'price',
        'number_of_likes', 'discount_rate', 'book_type',
        'file_path', 'file_type', 'file_size', 'category_id'
    ];

    // 🔗 العلاقة مع الأقسام
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // 🔗 العلاقة مع المستخدمين (pivot table)
    public function users()
    {
        return $this->belongsToMany(ReadingPlatformUser::class, 'book_user')
                    ->withPivot('liked', 'owned', 'downloaded_at')
                    ->withTimestamps();
    }

    // 🔗 العلاقة مع الأسئلة
    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    /**
     * Cascade Delete: حذف كل ما يتعلق بالكتاب تلقائياً
     * عند حذف الكتاب نفسه:
     * 1️⃣ حذف جميع الأسئلة المرتبطة بالكتاب
     * 2️⃣ حذف جميع الإجابات المرتبطة بكل سؤال
     * 3️⃣ حذف الملف المرفوع للكتاب إن وجد
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($book) {
            // حذف كل الأسئلة والإجابات المرتبطة
            foreach ($book->questions as $question) {
                $question->answers()->delete(); // حذف الإجابات أولاً
                $question->delete();            // ثم حذف السؤال نفسه
            }

            // حذف الملف المرفوع إن وجد
            if ($book->file_path && Storage::disk('local')->exists($book->file_path)) {
                Storage::disk('local')->delete($book->file_path);
            }
        });
    }
}
