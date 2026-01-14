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
        'likes_count',
        'status', // pending | accepted
    ];

    
    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

  
    public function owner()
    {
        return $this->belongsTo(ReadingPlatformUser::class, 'user_id');
    }

    
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
