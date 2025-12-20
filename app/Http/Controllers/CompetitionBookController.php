<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\CompetitionBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompetitionBookController extends Controller
{
    /**
     * مشاركة المستخدم في المسابقة
     * يسمح فقط إذا كانت المسابقة فعالة ووقتها شغال
     */
    public function store(Request $request, $competitionId)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        try {
            $request->validate([
                'title' => 'required|string|max:50',
                'file'  => 'required|file|mimes:pdf|max:20480',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'الرجاء وضع كافة البيانات المطلوبة'
            ], 422);
        }

        // التحقق من المسابقة
        $competition = Competition::find($competitionId);
        if (
            !$competition ||
            $competition->status !== 'active' ||
            now()->lt($competition->startdate) ||
            now()->gt($competition->enddate)
        ) {
            return response()->json([
                'message' => 'المسابقة غير متاحة حالياً'
            ], 403);
        }

        // منع المشاركة المكررة
        $alreadyParticipated = CompetitionBook::where('competition_id', $competitionId)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyParticipated) {
            return response()->json([
                'message' => 'لقد قمت بالمشاركة سابقًا في هذه المسابقة'
            ], 409);
        }

        // التحقق من الحد الأقصى
        if (
            CompetitionBook::where('competition_id', $competitionId)->count()
            >= $competition->max_user
        ) {
            return response()->json([
                'message' => 'اكتمل عدد المشاركين'
            ], 403);
        }

        // رفع الملف
        $path = $request->file('file')->store('competition_books');

        $book = CompetitionBook::create([
            'competition_id' => $competitionId,
            'user_id'        => $user->id,
            'title'          => $request->title,
            'file_path'      => $path,
            'file_type'      => 'pdf',
            'file_size'      => $request->file('file')->getSize(),
            'likes_count'    => 0,
        ]);

        return response()->json([
            'message' => 'تم رفع الكتاب بنجاح',
            'book' => $book
        ], 201);
    }

    /**
     * عرض كتب المسابقة للمستخدم
     */
    public function index($competitionId)
    {
        $competition = Competition::findOrFail($competitionId);

        if (
            $competition->status !== 'active' ||
            now()->lt($competition->startdate) ||
            now()->gt($competition->enddate)
        ) {
            return response()->json([
                'message' => 'المسابقة غير متاحة حالياً'
            ], 403);
        }

        $books = CompetitionBook::where('competition_id', $competitionId)
            ->orderByDesc('likes_count')
            ->select('competition_book_id', 'title', 'likes_count')
            ->get();

        return response()->json([
            'success' => true,
            'books' => $books
        ]);
    }

    /**
     * لايك / إلغاء لايك
     */
    public function like(Request $request, $id)
    {
        $user = $request->user();
        $book = CompetitionBook::findOrFail($id);
        $competition = Competition::findOrFail($book->competition_id);

        if (
            $competition->status !== 'active' ||
            now()->lt($competition->startdate) ||
            now()->gt($competition->enddate)
        ) {
            return response()->json([
                'message' => 'المسابقة غير متاحة حالياً'
            ], 403);
        }

        $result = $book->likedUsers()->toggle($user->id);
        $book->likes_count = $book->likedUsers()->count();
        $book->save();

        return response()->json([
            'liked' => in_array($user->id, $result['attached']),
            'likes_count' => $book->likes_count
        ]);
    }

    /**
     * تحميل كتاب المسابقة
     */
    public function download($id)
    {
        $book = CompetitionBook::findOrFail($id);
        $competition = Competition::findOrFail($book->competition_id);

        if (
            $competition->status !== 'active' ||
            now()->lt($competition->startdate) ||
            now()->gt($competition->enddate)
        ) {
            return response()->json([
                'message' => 'المسابقة غير متاحة حالياً'
            ], 403);
        }

        if (!Storage::exists($book->file_path)) {
            return response()->json(['message' => 'الملف غير موجود'], 404);
        }

        return response()->download(
            storage_path('app/' . $book->file_path),
            $book->title . '.pdf'
        );
    }

    /**
     * عرض الكتب مع اللايكات (للأدمن فقط)
     */
    public function adminLikes($competitionId)
    {
        $books = CompetitionBook::where('competition_id', $competitionId)
            ->with('likedUsers:id,name')
            ->get();

        return response()->json([
            'success' => true,
            'books' => $books
        ]);
    }

    /**
     * حذف كتاب (للأدمن فقط)
     */
    public function destroy(Request $request, $bookId)
    {
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $book = CompetitionBook::findOrFail($bookId);

        DB::table('competition_book_user')
            ->where('competition_book_id', $book->competition_book_id)
            ->delete();

        Storage::delete($book->file_path);
        $book->delete();

        return response()->json([
            'message' => 'تم حذف الكتاب بنجاح'
        ]);
    }
}
