<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use Illuminate\Http\Request;

class CompetitionController extends Controller
{
    /**
     * عرض المسابقات المتاحة للمستخدم العادي
     * يسمح فقط بالمسابقات الفعالة وحالياً ضمن تواريخها
     */
    public function index(Request $request)
    {
        if ($request->user()->user_type != 1) { // 1 = user عادي
            return response()->json([
                'message' => 'غير مسموح لك بعرض المسابقات كمشارك'
            ], 403);
        }

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
     * عرض جميع المسابقات (للأدمن فقط)
     */
    public function adminIndex(Request $request)
    {
        if ($request->user()->user_type != 2) { // 2 = admin
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        return response()->json([
            'success' => true,
            'competitions' => Competition::all()
        ]);
    }

    /**
     * إنشاء مسابقة (للأدمن فقط)
     */
    public function store(Request $request)
    {
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:50',
            'status' => 'required|in:active,inactive,finished',
            'startdate' => 'required|date',
            'enddate' => 'required|date|after_or_equal:startdate',
            'max_user' => 'required|integer|min:1',
        ]);

        $competition = Competition::create($request->all());

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
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

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
    public function destroy(Request $request, $id)
    {
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        Competition::findOrFail($id)->delete();

        return response()->json([
            'message' => 'تم حذف المسابقة بنجاح'
        ]);
    }
}
