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

  
    protected $table = 'reading_platform_users';

   
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

   
    protected $hidden = [
        'password',
        'remember_token',
    ];

    
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

  
    public function purchases()
    {
        return $this->belongsToMany(
            Purchasing::class,
            'user_purchasing',
            'user_id',
            'purchasing_id'
        )->withTimestamps();
    }

    
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

    
    public function requestBooks()
    {
        return $this->hasMany(RequestBook::class, 'user_id');
    }

    
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($user) {
            
            \App\Models\UserBookAnswer::where('user_id', $user->id)->delete();

            
            $books = $user->books()->get();
            foreach ($books as $book) {
              
                $user->books()->detach($book->id);

               
                $likes = $book->users()->wherePivot('liked', true)->count();
                $book->number_of_likes = $likes;
                $book->save();
            }

           
            $user->requestBooks()->delete();
        });
    }
}
