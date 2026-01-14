<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Competition extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'status', 'startdate', 'enddate', 'max_user'];


    
    public function competitionBooks()
    {
        return $this->hasMany(CompetitionBook::class);
    }
}
