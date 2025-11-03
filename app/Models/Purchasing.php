<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchasing extends Model
{
    use HasFactory;

    protected $fillable = ['purchase_date'];

    public function users()
    {
        return $this->belongsToMany(ReadingPlatformUser::class, 'user_purchasing', 'purchasing_id', 'user_id')
                    ->withTimestamps();
    }
}
