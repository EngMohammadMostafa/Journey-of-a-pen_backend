<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use Illuminate\Http\Request;

class CompetitionController extends Controller
{
    /**
     * عرض المسابقات للمستخدم
     * فقط المسابقات المتاحة (active)
     */
    public function index()
    {
        $competitions = Competition::where('status', 'active')
            ->whereDate('startdate', '<=', now())
            ->whereDate('enddate', '>=', now())
            ->get();

        return response()->json([
            'success' => true,
            'competitions' => $competitions
        ]);
    }

    /**
     * عرض جميع المسابقات للأدمن
     */
    public function adminIndex()
    {
        $competitions = Competition::all();

        return response()->json([
            'success' => true,
            'competitions' => $competitions
        ]);
    }
}
