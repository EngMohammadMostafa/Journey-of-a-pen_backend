<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class ReadingPlatformUser extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // اسم الجدول في قاعدة البيانات
    protected $table = 'reading_platform_users';

    // الحقول التي يمكن تعبئتها
    protected $fillable = [
        'username',
        'age',
        'email',
        'gender',
        'password',
        'user_type',
        'points',
        'purchases_count'
    ];

    // الحقول التي يتم إخفاؤها عند التحويل لـJSON
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // تحويل أنواع البيانات
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * 🔗 العلاقة مع جدول purchasing (M:N)
     * كل مستخدم يمكن أن يقوم بعدة عمليات شراء
     */
    public function purchases()
    {
        return $this->belongsToMany(Purchasing::class, 'user_purchasing', 'user_id', 'purchasing_id')
                    ->withTimestamps();
    }

    /**
     * 🔗 العلاقة مع الكتب (M:N)
     * كل مستخدم يمكن أن يحب أو يمتلك عدة كتب
     */
    public function books()
    {
        return $this->belongsToMany(Book::class, 'book_user', 'user_id', 'book_id')
                    ->withPivot('liked')
                    ->withTimestamps();
    }
}
