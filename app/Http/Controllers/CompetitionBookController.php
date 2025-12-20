<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\CompetitionBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CompetitionBookController extends Controller
{
    /**
     * رفع كتاب للمسابقة
     */
    public function store(Request $request, $competitionId)
    {
        $user = $request->user();

        $competition = Competition::find($competitionId);
        if (
            !$competition ||
            $competition->status !== 'active' ||
            now()->lt($competition->startdate) ||
            now()->gt($competition->enddate)
        ) {
            return response()->json(['message' => 'المسابقة غير متاحة'], 403);
        }

        if (CompetitionBook::where('competition_id', $competitionId)
            ->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'مشارك مسبقًا'], 409);
        }

        $path = $request->file('file')->store('competition_books');

        $book = CompetitionBook::create([
            'competition_id' => $competitionId,
            'user_id' => $user->id,
            'title' => $request->title,
            'file_path' => $path,
            'likes_count' => 0
        ]);

        return response()->json(['book' => $book], 201);
    }

    /**
     * عرض كتب المسابقة للمستخدم
     */
    public function index($competitionId)
    {
        $competition = Competition::findOrFail($competitionId);

        if ($competition->status !== 'active') {
            return response()->json(['message' => 'غير متاحة'], 403);
        }

        return response()->json([
            'books' => CompetitionBook::where('competition_id', $competitionId)
                ->orderByDesc('likes_count')
                ->get(['competition_book_id', 'title', 'likes_count'])
        ]);
    }

    /**
     * لايك / إلغاء لايك
     */
    public function like(Request $request, $id)
    {
        $book = CompetitionBook::findOrFail($id);
        $competition = Competition::findOrFail($book->competition_id);

        if ($competition->status !== 'active') {
            return response()->json(['message' => 'غير متاحة'], 403);
        }

        $result = $book->likedUsers()->toggle($request->user()->id);
        $book->likes_count = $book->likedUsers()->count();
        $book->save();

        return response()->json([
            'liked' => in_array($request->user()->id, $result['attached']),
            'likes_count' => $book->likes_count
        ]);
    }

    /**
     * تحميل كتاب
     */
    public function download($id)
    {
        $book = CompetitionBook::findOrFail($id);

        return response()->download(
            storage_path('app/' . $book->file_path),
            $book->title . '.pdf'
        );
    }

    /**
     * ⭐ عرض تفاصيل مسابقة كاملة (للأدمن)
     */
    public function adminCompetitionDetails(Request $request, $competitionId)
    {
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $competition = Competition::findOrFail($competitionId);

        $books = CompetitionBook::where('competition_id', $competitionId)
            ->with(['user:id,name', 'likedUsers:id,name'])
            ->orderByDesc('likes_count')
            ->get();

        return response()->json([
            'competition' => $competition,
            'books' => $books
        ]);
    }

    /**
     * ⭐ عرض لايكات كتاب معيّن (للأدمن)
     */
    public function adminBookLikes(Request $request, $bookId)
    {
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $book = CompetitionBook::with('likedUsers:id,name')->findOrFail($bookId);

        return response()->json([
            'book' => $book->title,
            'likes_count' => $book->likes_count,
            'liked_users' => $book->likedUsers
        ]);
    }

    /**
     * حذف كتاب
     */
    public function destroy(Request $request, $id)
    {
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $book = CompetitionBook::findOrFail($id);
        DB::table('competition_book_user')->where('competition_book_id', $id)->delete();
        Storage::delete($book->file_path);
        $book->delete();

        return response()->json(['message' => 'تم الحذف']);
    }
}
