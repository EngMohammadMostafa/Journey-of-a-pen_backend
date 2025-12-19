<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Competition extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'startdate',
        'enddate',
        'max_user'
    ];

    /**
     * العلاقة:
     * المسابقة الواحدة تحتوي على عدة كتب مسابقة
     */
    public function competitionBooks()
    {
        return $this->hasMany(CompetitionBook::class);
    }
}
