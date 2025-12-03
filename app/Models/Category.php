<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    // الحقول التي يمكن تعبئتها بشكل جماعي
    protected $fillable = ['name'];

    /**
     * العلاقة مع الكتب
     * كل قسم يمكن أن يحتوي على عدة كتب
     */
    public function books()
    {
        return $this->hasMany(Book::class);
    }

    /**
     * Cascade Delete: عند حذف القسم
     * 1️⃣ سيتم حذف جميع الكتب المرتبطة بهذا القسم
     * 2️⃣ كل كتاب عند حذفه سيحذف أسئلته وإجاباته وملفاته تلقائيًا (انظر Book::deleting)
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($category) {
            // استخدام chunkById لتقليل استهلاك الذاكرة عند وجود عدد كبير من الكتب
            $category->books()->chunkById(50, function ($books) {
                foreach ($books as $book) {
                    $book->delete(); // يشغل الـ deleting event في Book
                }
            });
        });
    }
}
