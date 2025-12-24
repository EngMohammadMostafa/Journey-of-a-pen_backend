<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

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
     * المستخدم الواحد يمكنه تقديم عدة طلبات
     */
    public function requestBooks()
    {
        return $this->hasMany(RequestBook::class, 'user_id');
    }
}
