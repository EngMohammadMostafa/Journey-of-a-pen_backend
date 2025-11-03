<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Repoint extends Model
{
    use HasFactory;

    protected $fillable = ['number_of_points', 'repoint_date', 'reward_id'];

    public function reward()
    {
        return $this->belongsTo(Reward::class, 'reward_id');
    }
}
