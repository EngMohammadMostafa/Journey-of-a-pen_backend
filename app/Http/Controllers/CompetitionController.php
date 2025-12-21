<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CompetitionController extends Controller
{
    /* =========================
       قائمة المسابقات للمستخدم العادي
       ========================= */
    public function index(Request $request)
    {
        // تحقق من نوع المستخدم: 1 = مستخدم عادي
        if ($request->user()->user_type != 1) {
            return response()->json(['message' => 'غير مسموح'], 403);
        }

        $competitions = Competition::where('status', 'active')
            ->where('startdate', '<=', now())
            ->where('enddate', '>=', now())
            ->get();

        return response()->json([
            'success' => true,
            'competitions' => $competitions
        ]);
    }

    /* =========================
       قائمة المسابقات للـ Admin
       ========================= */
    public function adminIndex(Request $request)
    {
        // تحقق من نوع المستخدم: 2 = أدمن
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        return response()->json([
            'success' => true,
            'competitions' => Competition::all()
        ]);
    }

    /* =========================
       إنشاء مسابقة جديدة
       ========================= */
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
            'message' => 'تم إنشاء المسابقة',
            'competition' => $competition
        ], 201);
    }

    /* =========================
       تعديل مسابقة
       ========================= */
    public function update(Request $request, $id)
    {
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $competition = Competition::findOrFail($id);
        $competition->update($request->all());

        return response()->json([
            'message' => 'تم التعديل',
            'competition' => $competition
        ]);
    }

    /* =========================
       حذف مسابقة وكتبها
       ========================= */
    public function destroy(Request $request, $id)
    {
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $competition = Competition::findOrFail($id);

        foreach ($competition->competitionBooks as $book) {
            // حذف أي بيانات للمستخدمين مرتبطة بكتاب المسابقة
            DB::table('competition_book_user')
                ->where('competition_book_id', $book->competition_book_id)
                ->delete();

            // حذف الملف من التخزين
            Storage::delete($book->file_path);

            // حذف الكتاب نفسه
            $book->delete();
        }

        // حذف المسابقة نفسها
        $competition->delete();

        return response()->json(['message' => 'تم الحذف']);
    }

    /* =========================
       🔹 API جديد: العدد الكلي للمسابقات للـ Admin
       ========================= */
    public function adminGetTotalCompetitions(Request $request)
    {
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $total = Competition::count();

        return response()->json([
            'success' => true,
            'total_competitions' => $total
        ]);
    }
}
