<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ReadingPlatformUser;

class CompetitionBook extends Model
{
    use HasFactory;

    protected $primaryKey = 'competition_book_id';

    protected $fillable = [
        'competition_id',
        'user_id',
        'title',
        'file_path',
        'file_type',
        'file_size',
        'likes_count'
    ];

    /**
     * كتاب المسابقة ينتمي إلى مسابقة واحدة
     */
    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * صاحب الكتاب (المستخدم الذي رفعه)
     */
    public function owner()
    {
        return $this->belongsTo(ReadingPlatformUser::class, 'user_id');
    }

    /**
     * المستخدمون الذين وضعوا لايك على الكتاب
     */
    public function likedUsers()
    {
        return $this->belongsToMany(
            ReadingPlatformUser::class,
            'competition_book_user',
            'competition_book_id',
            'user_id'
        )->withTimestamps();
    }
}
