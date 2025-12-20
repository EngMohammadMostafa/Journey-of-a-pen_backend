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

    /**
     * إنشاء مسابقة جديدة (للأدمن فقط)
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'status' => 'required|in:active,inactive,finished',
            'startdate' => 'required|date',
            'enddate' => 'required|date|after_or_equal:startdate',
            'max_user' => 'required|integer|min:1',
        ]);

        $competition = Competition::create([
            'name' => $request->name,
            'status' => $request->status,
            'startdate' => $request->startdate,
            'enddate' => $request->enddate,
            'max_user' => $request->max_user,
        ]);

        return response()->json([
            'message' => 'تم إنشاء المسابقة بنجاح',
            'competition' => $competition
        ], 201);
    }

    /**
     * تعديل مسابقة (للأدمن فقط)
     */
    public function update(Request $request, $id)
    {
        $competition = Competition::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:50',
            'status' => 'sometimes|in:active,inactive,finished',
            'startdate' => 'sometimes|date',
            'enddate' => 'sometimes|date|after_or_equal:startdate',
            'max_user' => 'sometimes|integer|min:1',
        ]);

        $competition->update($request->all());

        return response()->json([
            'message' => 'تم تعديل المسابقة بنجاح',
            'competition' => $competition
        ]);
    }

    /**
     * حذف مسابقة (للأدمن فقط)
     */
    public function destroy($id)
    {
        $competition = Competition::findOrFail($id);
        $competition->delete();

        return response()->json([
            'message' => 'تم حذف المسابقة بنجاح'
        ]);
    }
}
