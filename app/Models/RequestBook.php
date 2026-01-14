<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestBook extends Model
{
    use HasFactory;

    protected $table = 'request_books';

  
    protected $primaryKey = 'request_id';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'price',
        'book_type',
        'file_path',
        'file_type',
        'file_size',
        'status',
    ];

   
    public function user()
    {
        return $this->belongsTo(\App\Models\ReadingPlatformUser::class, 'user_id');
    }
}
