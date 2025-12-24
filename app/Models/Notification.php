<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    // المفتاح الأساسي لجدول الإشعارات
    protected $primaryKey = 'notification_id';

    // الحقول القابلة للملء جماعياً
    protected $fillable = [
        'title',
        'content',
        'user_id' // الاداري الذي أنشأ الإشعار
    ];

    /**
     * العلاقة مع المستخدم (الاداري)
     * Notification -> belongsTo Admin(User)
     */
    public function admin()
    {
        return $this->belongsTo(
            ReadingPlatformUser::class,
            'user_id', // الحقل في جدول notifications
            'id'       // المفتاح الأساسي الحقيقي في جدول المستخدمين
        );
    }
}
