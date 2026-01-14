<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    
    protected $primaryKey = 'notification_id';

   
    protected $fillable = [
        'title',
        'content',
        'user_id' 
    ];

   
    public function admin()
    {
        return $this->belongsTo(
            ReadingPlatformUser::class,
            'user_id', 
            'id'       
        );
    }
}
