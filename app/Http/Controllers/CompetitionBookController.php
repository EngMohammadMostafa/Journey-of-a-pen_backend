<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\CompetitionBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CompetitionBookController extends Controller
{
    /**
     * المستخدم يرفع كتاب للمسابقة
     */
    public function store(Request $request, $competitionId)
    {
        $competition = Competition::find($competitionId);

        if (!$competition || $competition->status !== 'active') {
            return response()->json(['message' => 'المسابقة غير متاحة'], 403);
        }

        // التحقق من عدد المشاركين
        $participantsCount = CompetitionBook::where('competition_id', $competitionId)->count();
        if ($participantsCount >= $competition->max_user) {
            return response()->json(['message' => 'تم الوصول للعدد الأعظمي للمشاركين'], 403);
        }

        // Validation للملف
        $request->validate([
            'title' => 'required|string|max:50',
            'file'  => 'required|file|mimes:pdf|max:20480',
        ], [
            'file.required' => 'يجب إرفاق ملف الكتاب (PDF)',
            'file.mimes' => 'نوع الملف يجب أن يكون PDF فقط',
        ]);

        $file = $request->file('file');
        $path = $file->store('competition_books');

        $book = CompetitionBook::create([
            'competition_id' => $competitionId,
            'user_id'        => $request->user()->id,
            'title'          => $request->title,
            'file_path'      => $path,
            'file_type'      => $file->extension(),
            'file_size'      => $file->getSize(),
            'likes_count'    => 0,
        ]);

        return response()->json([
            'message' => 'تم رفع الكتاب للمسابقة بنجاح',
            'book' => $book
        ], 201);
    }

    /**
     * عرض كتب المسابقة للمستخدم مع رابط التحميل
     */
    public function index($competitionId)
    {
        $books = CompetitionBook::where('competition_id', $competitionId)
            ->select('competition_book_id', 'title', 'likes_count', 'file_path')
            ->orderByDesc('likes_count')
            ->get();

        return response()->json([
            'success' => true,
            'books' => $books
        ]);
    }

    /**
     * وضع لايك أو إلغاء اللايك
     */
    public function like(Request $request, $competitionBookId)
    {
        $userId = $request->user()->id;

        $existing = DB::table('competition_book_user')
            ->where('competition_book_id', $competitionBookId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            // إذا موجود مسبقاً، نقوم بعكس حالة اللايك
            $newLiked = !$existing->liked;
            DB::table('competition_book_user')
                ->where('competition_book_id', $competitionBookId)
                ->where('user_id', $userId)
                ->update([
                    'liked' => $newLiked,
                    'updated_at' => now(),
                ]);

            // تحديث عدد اللايكات
            $likesCount = DB::table('competition_book_user')
                ->where('competition_book_id', $competitionBookId)
                ->where('liked', true)
                ->count();

            CompetitionBook::where('competition_book_id', $competitionBookId)->update(['likes_count' => $likesCount]);

            return response()->json([
                'message' => $newLiked ? 'تم وضع لايك' : 'تم إلغاء اللايك',
                'likes_count' => $likesCount
            ]);
        }

        // إذا لم يضع لايك من قبل
        DB::table('competition_book_user')->insert([
            'competition_book_id' => $competitionBookId,
            'user_id' => $userId,
            'liked' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // تحديث عدد اللايكات
        CompetitionBook::where('competition_book_id', $competitionBookId)->increment('likes_count');

        return response()->json([
            'message' => 'تم وضع لايك',
            'likes_count' => CompetitionBook::find($competitionBookId)->likes_count
        ]);
    }

    /**
     * عرض اللايكات للأدمن مع أسماء المستخدمين
     */
    public function adminLikes($competitionBookId)
    {
        $book = CompetitionBook::with('likedUsers:id,name')
            ->where('competition_book_id', $competitionBookId)
            ->firstOrFail();

        return response()->json([
            'likes_count' => $book->likes_count,
            'users' => $book->likedUsers
        ]);
    }
}
