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

        if (! $competition || $competition->status !== 'active') {
            return response()->json(['message' => 'المسابقة غير متاحة'], 403);
        }

        // التحقق من عدد المشاركين
        $participantsCount = CompetitionBook::where('competition_id', $competitionId)->count();
        if ($participantsCount >= $competition->max_user) {
            return response()->json(['message' => 'تم الوصول للعدد الأعظمي للمشاركين'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:50',
            'file'  => 'required|file|mimes:pdf,epub|max:20480',
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
     * عرض كتب المسابقة للمستخدم
     */
    public function index($competitionId)
    {
        $books = CompetitionBook::where('competition_id', $competitionId)
            ->select('competition_book_id', 'title', 'likes_count')
            ->orderByDesc('likes_count')
            ->get();

        return response()->json([
            'success' => true,
            'books' => $books
        ]);
    }

    /**
     * وضع لايك (مرة واحدة فقط)
     */
    public function like(Request $request, $competitionBookId)
    {
        $userId = $request->user()->id;

        $exists = DB::table('competition_book_user')
            ->where('competition_book_id', $competitionBookId)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'لقد قمت بالإعجاب مسبقًا'], 409);
        }

        DB::table('competition_book_user')->insert([
            'competition_book_id' => $competitionBookId,
            'user_id' => $userId,
            'liked' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CompetitionBook::where('competition_book_id', $competitionBookId)
            ->increment('likes_count');

        return response()->json(['message' => 'تم وضع لايك']);
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
