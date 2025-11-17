<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Category;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\URL;

class BookController extends Controller
{
    /**
     * 🟢 عرض كل الأقسام مع الكتب (موجودة عندك)
     */
    public function getCategories()
    {
        $categories = Category::with('books')->get();

        return response()->json([
            'success' => true,
            'categories' => $categories
        ]);
    }

    /**
     * 🟢 عرض كتب قسم محدد (موجودة)
     */
    public function getBooksByCategory($categoryId)
    {
        $category = Category::with('books')->find($categoryId);

        if (!$category) {
            return response()->json(['message' => 'القسم غير موجود'], 404);
        }

        return response()->json([
            'success' => true,
            'category' => $category->name,
            'books' => $category->books
        ]);
    }

    /**
     * عرض كل الكتب (API: GET /api/books)
     * يعيد قائمة الكتب مع علاقة القسم (يمكن إضافة pagination لاحقاً)
     */
    public function index()
    {
        $books = Book::with('category')->get();

        return response()->json([
            'success' => true,
            'books' => $books
        ]);
    }

    /**
     * تفاصيل كتاب محدد (API: GET /api/books/{id})
     */
    public function show($id)
    {
        $book = Book::with('category')->find($id);

        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        return response()->json([
            'success' => true,
            'book' => $book
        ]);
    }

    /**
     * جلب الكتب المملوكة للمستخدم (API: GET /api/me/books)
     * يقرأ جدول book_user حيث owned = 1
     */
    public function getUserBooks(Request $request)
    {
        $user = $request->user();

        $owned = DB::table('book_user')
            ->where('user_id', $user->id)
            ->where('owned', 1)
            ->pluck('book_id')
            ->toArray();

        $books = Book::whereIn('id', $owned)->get();

        return response()->json([
            'success' => true,
            'books' => $books
        ]);
    }

    /**
     * إنشاء كتاب في قسم معين (Admin)
     * Route: POST /api/admin/categories/{categoryId}/books
     * Body: multipart/form-data (file + text fields)
     */
    public function store(Request $request, $categoryId)
    {
        // تأكد أن القسم موجود
        $category = Category::find($categoryId);
        if (! $category) {
            return response()->json(['message' => 'القسم غير موجود'], 404);
        }

        // قواعد التحقق
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'nullable|numeric|min:0',
            'book_type' => 'required|in:free,paid',
            'file' => 'required|file|mimes:pdf,epub|max:20480', // 20MB
            'discount_rate' => 'nullable|numeric|min:0|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // احفظ الملف
        $file = $request->file('file');
        $path = $file->store('books'); // storage/app/books

        // أنشئ السجل في جدول الكتب
        $book = Book::create([
            'author' => $request->author,
            'title' => $request->title,
            'description' => $request->description,
            'price' => $request->price ?? 0,
            'number_of_likes' => 0,
            'discount_rate' => $request->discount_rate ?? 0,
            'book_type' => $request->book_type,
            'file_path' => $path,
            'file_type' => $file->extension(),
            'file_size' => $file->getSize(),
            'category_id' => $categoryId
        ]);

        return response()->json(['message' => 'تم إضافة الكتاب بنجاح', 'book' => $book], 201);
    }

    /**
     * توليد رابط تحميل مؤقت (signed URL) صالح لمدة 10 دقائق
     * يُستدعى بعد التحقق من امتلاك/تحميل الكتاب.
     * Route: POST /api/books/{id}/download (auth)
     */
    public function generateDownloadLink(Request $request, $id)
    {
        $user = $request->user();
        $book = Book::find($id);

        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        // تحقق الملكية للكتب المدفوعة
        if ($book->book_type === 'paid') {
            $pivot = DB::table('book_user')
                ->where('book_id', $book->id)
                ->where('user_id', $user->id)
                ->first();

            if (!$pivot || !$pivot->owned) {
                return response()->json(['message' => 'يجب الحصول على امتلاك الكتاب أولاً'], 403);
            }

            if (!$pivot->downloaded_at) {
                DB::table('book_user')
                    ->where('book_id', $book->id)
                    ->where('user_id', $user->id)
                    ->update(['downloaded_at' => now()]);
            }
        } else {
            // إذا الكتاب مجاني: نضع owned=true و downloaded_at الآن
            $exists = DB::table('book_user')
                ->where('book_id', $book->id)
                ->where('user_id', $user->id)
                ->exists();

            if (!$exists) {
                DB::table('book_user')->insert([
                    'book_id' => $book->id,
                    'user_id' => $user->id,
                    'liked' => false,
                    'owned' => true,
                    'downloaded_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('book_user')
                    ->where('book_id', $book->id)
                    ->where('user_id', $user->id)
                    ->update(['owned' => true, 'downloaded_at' => now(), 'updated_at' => now()]);
            }
        }

        // تحقق من وجود الملف فعليًا في التخزين المحلي
        if (!$book->file_path || !Storage::disk('local')->exists($book->file_path)) {
            return response()->json(['message' => 'ملف الكتاب غير موجود على الخادم'], 404);
        }

        // أنشئ signed temporary route صالح 10 دقائق
        $signedUrl = URL::temporarySignedRoute(
            'books.serveDownload',
            now()->addMinutes(10),
            ['id' => $book->id, 'userId' => $user->id]
        );

        return response()->json([
            'success' => true,
            'download_url' => $signedUrl,
            'expires_in_seconds' => 600,
            'message' => 'رابط تحميل صالح مؤقتًا تم إنشاؤه'
        ]);
    }

    /**
     * Serve the file if the signed URL is valid.
     * Route (public): GET /api/books/{id}/serve-download/{userId}
     */
    public function serveDownload(Request $request, $id, $userId)
    {
        if (! $request->hasValidSignature()) {
            return response()->json(['message' => 'الرابط منتهي الصلاحية أو غير صالح'], 403);
        }

        $book = Book::find($id);
        if (! $book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        $filePath = storage_path('app/' . $book->file_path);

        if (!file_exists($filePath)) {
            return response()->json(['message' => 'ملف الكتاب غير موجود على الخادم'], 404);
        }

        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $book->title . '.pdf"'
        ]);
    }

    /**
     * 🟢 تنزيل مباشر - (قد لا تستخدمه إن اعتمدت على signed URL)
     */
    public function downloadBook(Request $request, $bookId)
    {
        $user = Auth::user();
        $book = Book::find($bookId);

        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        if ($book->book_type === 'paid' && !$this->checkPurchase($user->id, $book->id)) {
            return response()->json([
                'message' => 'يجب شراء هذا الكتاب أولاً قبل تحميله'
            ], 403);
        }

        if (!Storage::disk('local')->exists($book->file_path)) {
            return response()->json(['message' => 'ملف الكتاب غير موجود في الخادم'], 404);
        }

        $url = Storage::temporaryUrl($book->file_path, now()->addMinutes(10));

        return response()->json([
            'success' => true,
            'message' => 'تم السماح بتحميل الكتاب',
            'download_url' => $url
        ]);
    }

    /**
     * 🔒 التحقق من شراء الكتاب (مساعدة داخلية)
     */
    private function checkPurchase($userId, $bookId)
    {
        return Purchase::where('user_id', $userId)
            ->where('book_id', $bookId)
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * ❤️ تسجيل/إلغاء إعجاب بالكتاب
     */
    public function toggleLike($bookId)
    {
        $user = Auth::user();
        $book = Book::find($bookId);

        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        $liked = $book->users()->where('user_id', $user->id)->exists();

        if ($liked) {
            $book->users()->detach($user->id);
            $book->decrement('number_of_likes');
            $message = 'تم إزالة الإعجاب';
        } else {
            $book->users()->attach($user->id);
            $book->increment('number_of_likes');
            $message = 'تم تسجيل الإعجاب';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'likes_count' => $book->number_of_likes
        ]);
    }

    /**
     * (Admin) تعديل معلومات كتاب
     * Route: PUT /api/books/{id}
     */
    public function update(Request $request, $id)
    {
        $book = Book::find($id);
        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        $data = $request->only(['title','author','description','price','book_type','discount_rate','category_id']);

        $book->update($data);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث معلومات الكتاب',
            'book' => $book
        ]);
    }

    /**
     * (Admin) حذف كتاب
     * Route: DELETE /api/books/{id}
     * يحذف الملف من التخزين إن وُجد ثم يحذف السجل
     */
    public function destroy($id)
    {
        $book = Book::find($id);
        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        // حذف الملف من التخزين لو موجود
        if ($book->file_path && Storage::disk('local')->exists($book->file_path)) {
            Storage::disk('local')->delete($book->file_path);
        }

        $book->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الكتاب'
        ]);
    }
}
