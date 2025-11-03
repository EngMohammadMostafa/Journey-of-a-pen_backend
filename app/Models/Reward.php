<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'number_of_points', 'description', 'user_id'];

    public function user()
    {
        return $this->belongsTo(ReadingPlatformUser::class, 'user_id');
    }

    public function repoints()
    {
        return $this->hasMany(Repoint::class, 'reward_id');
    }
}
