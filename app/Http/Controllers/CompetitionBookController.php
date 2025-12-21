<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\CompetitionBook;
use App\Models\Book;
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

        $uploadedFile = $request->file('file');
        $path = $uploadedFile->store('competition_books');

        $book = CompetitionBook::create([
            'competition_id' => $competitionId,
            'user_id' => $user->id,
            'title' => $request->title,
            'file_path' => $path,
            'file_type' => $uploadedFile->getClientOriginalExtension(),
            'file_size' => $uploadedFile->getSize(),
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
            ->with(['owner:id,username', 'likedUsers:id,username'])
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

        $book = CompetitionBook::with('likedUsers:id,username')
            ->findOrFail($bookId);

        return response()->json([
            'book' => $book->title,
            'likes_count' => $book->likes_count,
            'liked_users' => $book->likedUsers
        ]);
    }

    /**
     * حذف كتاب + حذف لايكات المستخدم داخل نفس المسابقة فقط
     */
    public function destroy(Request $request, $id)
    {
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        DB::beginTransaction();

        try {
            $book = CompetitionBook::findOrFail($id);

            $userId = $book->user_id;
            $competitionId = $book->competition_id;

            $competitionBookIds = CompetitionBook::where('competition_id', $competitionId)
                ->pluck('competition_book_id');

            DB::table('competition_book_user')
                ->where('user_id', $userId)
                ->whereIn('competition_book_id', $competitionBookIds)
                ->delete();

            CompetitionBook::whereIn('competition_book_id', $competitionBookIds)
                ->each(function ($b) {
                    $b->likes_count = DB::table('competition_book_user')
                        ->where('competition_book_id', $b->competition_book_id)
                        ->count();
                    $b->save();
                });

            Storage::delete($book->file_path);
            $book->delete();

            DB::commit();

            return response()->json([
                'message' => 'تم حذف المستخدم من المسابقة مع كتابه ولايكاته داخل المسابقة'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'خطأ أثناء الحذف'], 500);
        }
    }

    /**
     * إضافة كتاب من المسابقة إلى منصة الكتب (للأدمن)
     */
    public function addToPlatform(Request $request, $id)
    {
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $competitionBook = CompetitionBook::with('owner')->findOrFail($id);

        // نسخ الملف إلى مجلد المنصة
        $newFilePath = 'books/' . basename($competitionBook->file_path);
        Storage::copy($competitionBook->file_path, $newFilePath);

        // إنشاء الكتاب في منصة الكتب
        $book = Book::create([
            'author' => $competitionBook->owner->username, // اسم صاحب الكتاب
            'title' => $competitionBook->title,
            'description' => $request->description ?? '',
            'price' => $request->price ?? 0,
            'number_of_likes' => 0,
            'book_type' => $request->book_type ?? 'normal',
            'file_path' => $newFilePath,
            'file_type' => $competitionBook->file_type,
            'file_size' => $competitionBook->file_size,
            'category_id' => $request->category_id ?? 1, // يمكن تحديد default category
        ]);

        return response()->json([
            'message' => 'تم إضافة الكتاب إلى المنصة بنجاح',
            'book' => $book
        ], 201);
    }
}
