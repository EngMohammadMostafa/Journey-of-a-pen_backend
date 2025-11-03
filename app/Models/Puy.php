<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Puy extends Model
{
    use HasFactory;

    protected $fillable = ['status', 'date', 'user_id'];

    public function user()
    {
        return $this->belongsTo(ReadingPlatformUser::class, 'user_id');
    }
}
