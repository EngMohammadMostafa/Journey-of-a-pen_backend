<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\CompetitionBook;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CompetitionBookController extends Controller
{
    /**
     * رفع كتاب للمسابقة
     * ✅ فقط للمستخدم العادي
     */
    public function store(Request $request, $competitionId)
    {
        $user = $request->user();

        if ($user->user_type != 1) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        // تحقق من صحة البيانات
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'file'  => 'required|file|mimes:pdf|max:10240',
        ], [
            'title.required' => 'الرجاء إدخال عنوان الكتاب',
            'file.required'  => 'الرجاء إرفاق ملف الكتاب',
            'file.mimes'     => 'الرجاء رفع ملف بصيغة PDF فقط',
            'file.max'       => 'حجم الملف يجب ألا يتجاوز 10 ميغابايت',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'فشل رفع الكتاب',
                'errors'  => $validator->errors()
            ], 422);
        }

        // تحقق من المسابقة
        $competition = Competition::find($competitionId);
        if (
            !$competition ||
            $competition->status !== 'active' ||
            now()->lt($competition->startdate) ||
            now()->gt($competition->enddate)
        ) {
            return response()->json(['message' => 'المسابقة غير متاحة'], 403);
        }

        // تحقق من العدد الأقصى للمشاركين
        $currentCount = CompetitionBook::where('competition_id', $competitionId)->count();
        if ($currentCount >= $competition->max_user) {
            return response()->json(['message' => 'عدد المشاركين مكتمل'], 403);
        }

        // تحقق من مشاركة سابقة
        if (CompetitionBook::where('competition_id', $competitionId)
            ->where('user_id', $user->id)
            ->exists()) {
            return response()->json(['message' => 'لقد شاركت مسبقًا في هذه المسابقة'], 409);
        }

        // رفع الملف وتخزينه
        $uploadedFile = $request->file('file');
        $path = $uploadedFile->store('competition_books');

        CompetitionBook::create([
            'competition_id' => $competitionId,
            'user_id'        => $user->id,
            'title'          => $request->title,
            'file_path'      => $path,
            'file_type'      => 'pdf',
            'file_size'      => $uploadedFile->getSize(),
            'likes_count'    => 0,
            'status'         => 'pending', // تلقائيًا ينتظر مراجعة الإدارة
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال الكتاب للمراجعة بانتظار موافقة الإدارة'
        ], 201);
    }

    /**
     * عرض الكتب المقبولة للمستخدم
     */
    public function index($competitionId)
    {
        $competition = Competition::findOrFail($competitionId);

        if ($competition->status !== 'active') {
            return response()->json(['message' => 'المسابقة غير متاحة'], 403);
        }

        return response()->json([
            'books' => CompetitionBook::where('competition_id', $competitionId)
                ->where('status', 'accepted')
                ->orderByDesc('likes_count')
                ->get(['competition_book_id', 'title', 'likes_count'])
        ]);
    }

    /**
     * لايك / إلغاء لايك على كتاب
     */
    public function like(Request $request, $id)
    {
        $book = CompetitionBook::findOrFail($id);

        if ($book->status !== 'accepted') {
            return response()->json(['message' => 'لا يمكن الإعجاب بهذا الكتاب'], 403);
        }

        $competition = $book->competition;
        if (
            !$competition ||
            $competition->status !== 'active' ||
            now()->lt($competition->startdate) ||
            now()->gt($competition->enddate)
        ) {
            return response()->json(['message' => 'المسابقة غير متاحة حالياً'], 403);
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
    public function download(Request $request, $id)
    {
        $book = CompetitionBook::findOrFail($id);

        if ($book->status === 'pending' && $request->user()->user_type != 2) {
            return response()->json(['message' => 'الكتاب غير متاح للتحميل'], 403);
        }

        return response()->download(
            storage_path('app/' . $book->file_path),
            $book->title . '.pdf'
        );
    }

    /**
     * تفاصيل المسابقة للأدمن
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
            'books'       => $books
        ]);
    }

    /**
     * قبول / رفض كتاب
     */
    public function approveOrReject(Request $request, $bookId)
    {
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:accepted,rejected'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $book = CompetitionBook::findOrFail($bookId);

        if ($request->status === 'rejected') {
            Storage::delete($book->file_path);
            $book->delete();
            return response()->json(['message' => 'تم رفض الكتاب وحذفه']);
        }

        $book->status = 'accepted';
        $book->save();

        return response()->json([
            'message' => 'تم قبول الكتاب',
            'book'    => $book
        ]);
    }

    /**
     * عرض عدد اللايكات ومعلومات المستخدمين الذين أعجبوا بالكتاب للأدمن
     */
    public function adminBookLikes(Request $request, $bookId)
    {
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $book = CompetitionBook::with('likedUsers:id,username')->findOrFail($bookId);

        return response()->json([
            'book_id'     => $book->competition_book_id,
            'title'       => $book->title,
            'likes_count' => $book->likes_count,
            'liked_users' => $book->likedUsers
        ]);
    }

    /**
     * حذف كتاب مشارك من المسابقة مع حذف اللايكات المرتبطة به فقط
     */
    public function destroy(Request $request, $bookId)
    {
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $book = CompetitionBook::with('likedUsers')->findOrFail($bookId);

        // حذف جميع اللايكات لهذا الكتاب فقط
        $book->likedUsers()->detach();

        // حذف الملف
        Storage::delete($book->file_path);

        // حذف السجل من قاعدة البيانات
        $book->delete();

        // إعادة ترتيب الكتب المتبقية حسب likes_count تنازليًا
        $competitionId = $book->competition_id;
        $books = CompetitionBook::where('competition_id', $competitionId)
                    ->orderByDesc('likes_count')
                    ->get();

        return response()->json([
            'message' => 'تم حذف الكتاب بنجاح، وتم إعادة ترتيب الكتب حسب التفاعل',
            'remaining_books' => $books
        ]);
    }

    /**
     * ⭐ جديد: إضافة كتاب مقبول من المسابقة إلى كتب المنصة
     * فقط للأدمن
     */
    public function addToPlatform(Request $request, $bookId)
    {
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'price'       => 'nullable|numeric|min:0',
            'book_type'   => 'required|in:free,paid',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // جلب الكتاب من المسابقة
        $competitionBook = CompetitionBook::findOrFail($bookId);

        if ($competitionBook->status !== 'accepted') {
            return response()->json(['message' => 'لا يمكن إضافة كتاب غير مقبول للمنصة'], 403);
        }

        // إنشاء الكتاب في المنصة باستخدام بيانات المسابقة + بيانات الأدمن
        $book = Book::create([
            'title'       => $competitionBook->title,
            'author'      => $competitionBook->owner->username ?? 'غير معروف',
            'description' => $request->description ?? '',
            'price'       => $request->price ?? 0,
            'book_type'   => $request->book_type,
            'file_path'   => $competitionBook->file_path,
            'file_type'   => $competitionBook->file_type,
            'file_size'   => $competitionBook->file_size,
            'category_id' => $request->category_id,
            'number_of_likes' => 0,
        ]);

        return response()->json([
            'message' => 'تم إضافة الكتاب للمنصة بنجاح',
            'book' => $book
        ], 201);
    }
}
