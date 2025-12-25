<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\DB;

class ReadingPlatformUser extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // اسم الجدول في قاعدة البيانات
    protected $table = 'reading_platform_users';

    // الحقول القابلة للتعبئة
    protected $fillable = [
        'username',
        'age',
        'email',
        'gender',
        'password',
        'user_type',       // 1 = مستخدم عادي، 2 = أدمن
        'points',
        'purchases_count'
    ];

    // الحقول المخفية عند الإخراج
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // تحويل الحقول لأنواع معينة
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * العلاقة مع جدول المشتريات (Purchasing)
     */
    public function purchases()
    {
        return $this->belongsToMany(
            Purchasing::class,
            'user_purchasing',
            'user_id',
            'purchasing_id'
        )->withTimestamps();
    }

    /**
     * العلاقة مع جدول الكتب (Book) عبر جدول الوسيط Book_User
     */
    public function books()
    {
        return $this->belongsToMany(
            Book::class,
            'book_user',
            'user_id',
            'book_id'
        )
        ->withPivot('liked', 'owned', 'downloaded_at')
        ->withTimestamps();
    }

    /**
     * العلاقة مع جدول طلبات رفع الكتب (RequestBook)
     */
    public function requestBooks()
    {
        return $this->hasMany(RequestBook::class, 'user_id');
    }

    /**
     * Boot method لحذف البيانات المرتبطة عند حذف المستخدم
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($user) {
            // 1️⃣ حذف جميع الأجوبة المرتبطة بالمستخدم
            \App\Models\UserBookAnswer::where('user_id', $user->id)->delete();

            // 2️⃣ إعادة حساب likes لكل كتاب تأثر
            $books = $user->books()->get();
            foreach ($books as $book) {
                // حذف السجل من جدول الوسيط قبل إعادة الحساب
                $user->books()->detach($book->id);

                // إعادة حساب عدد اللايكات
                $likes = $book->users()->wherePivot('liked', true)->count();
                $book->number_of_likes = $likes;
                $book->save();
            }

            // 3️⃣ حذف طلبات الكتب المعلقة أو المرفوضة فقط
            $user->requestBooks()->whereIn('status', ['pending', 'rejected'])->delete();
        });
    }
}
