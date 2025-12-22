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
     * - الكتاب الجديد يبدأ دائمًا بحالة 'pending'
     * - لا يمكن رفع كتاب إذا المستخدم رفع مسبقًا
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
            'likes_count' => 0,
            'status' => 'pending', // ⭐ جديد: كل كتاب مرفوع يبدأ "بانتظار موافقة الأدمن"
        ]);

        // ⭐ رسالة واضحة للمستخدم أن الكتاب أرسل للمراجعة
        return response()->json(['message' => 'تم إرسال الكتاب للمراجعة في انتظار موافقة الأدمن', 'book' => $book], 201);
    }

    /**
     * عرض كتب المسابقة للمستخدم
     * - يظهر فقط الكتب التي تم قبولها (status = accepted)
     */
    public function index($competitionId)
    {
        $competition = Competition::findOrFail($competitionId);

        if ($competition->status !== 'active') {
            return response()->json(['message' => 'غير متاحة'], 403);
        }

        return response()->json([
            'books' => CompetitionBook::where('competition_id', $competitionId)
                ->where('status', 'accepted') // ⭐ فقط الكتب المقبولة تظهر للمستخدم
                ->orderByDesc('likes_count')
                ->get(['competition_book_id', 'title', 'likes_count'])
        ]);
    }

    /**
     * لايك / إلغاء لايك
     * - يمكن فقط على الكتب المقبولة
     */
    public function like(Request $request, $id)
    {
        $book = CompetitionBook::findOrFail($id);

        if ($book->status !== 'accepted') {
            return response()->json(['message' => 'غير مسموح باللايك على هذا الكتاب'], 403);
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
     * - يمكن فقط تحميل الكتب المقبولة للمستخدمين
     * - يمكن تحميل الكتب المعلقة فقط للأدمن
     */
    public function download(Request $request, $id)
    {
        $book = CompetitionBook::findOrFail($id);

        if ($book->status === 'pending' && $request->user()->user_type != 2) {
            return response()->json(['message' => 'الكتاب غير متاح للتحميل'], 403);
        }

        if ($book->status === 'accepted' && $request->user()->user_type == 1) {
            $competition = Competition::findOrFail($book->competition_id);
            if ($competition->status !== 'active' || now()->lt($competition->startdate) || now()->gt($competition->enddate)) {
                return response()->json(['message' => 'المسابقة غير متاحة'], 403);
            }
        }

        return response()->download(storage_path('app/' . $book->file_path), $book->title . '.pdf');
    }

    /**
     * ⭐ عرض تفاصيل مسابقة كاملة (للأدمن)
     * - يشمل كل الكتب (pending + accepted)
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

        $book = CompetitionBook::with('likedUsers:id,username')->findOrFail($bookId);

        return response()->json([
            'book' => $book->title,
            'status' => $book->status, // ⭐ إضافة الحالة للعرض
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

            return response()->json(['message' => 'تم حذف المستخدم من المسابقة مع كتابه ولايكاته داخل المسابقة']);

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

        $newFilePath = 'books/' . basename($competitionBook->file_path);
        Storage::copy($competitionBook->file_path, $newFilePath);

        $book = Book::create([
            'author' => $competitionBook->owner->username,
            'title' => $competitionBook->title,
            'description' => $request->description ?? '',
            'price' => $request->price ?? 0,
            'number_of_likes' => 0,
            'book_type' => $request->book_type ?? 'normal',
            'file_path' => $newFilePath,
            'file_type' => $competitionBook->file_type,
            'file_size' => $competitionBook->file_size,
            'category_id' => $request->category_id ?? 1,
        ]);

        return response()->json(['message' => 'تم إضافة الكتاب إلى المنصة بنجاح', 'book' => $book], 201);
    }

    /**
     * ⭐ جديد: قبول / رفض كتاب من قبل الأدمن
     */
    public function approveOrReject(Request $request, $bookId)
    {
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $request->validate([
            'status' => 'required|in:accepted,rejected'
        ]);

        $book = CompetitionBook::findOrFail($bookId);

        if ($request->status === 'rejected') {
            Storage::delete($book->file_path);
            $book->delete();
            return response()->json(['message' => 'تم رفض الكتاب وحذفه بنجاح']);
        }

        // قبول الكتاب
        $book->status = 'accepted';
        $book->save();

        return response()->json(['message' => 'تم قبول الكتاب بنجاح', 'book' => $book]);
    }
}
