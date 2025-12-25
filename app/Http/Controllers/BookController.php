<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\URL;

class BookController extends Controller
{
    /* =========================
       الأقسام
       ========================= */
    public function getCategories()
    {
        $categories = Category::with('books')->get();

        return response()->json([
            'success' => true,
            'categories' => $categories
        ]);
    }

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

    /* =========================
       عرض الكتب
       ========================= */
    public function index()
    {
        $books = Book::with('category')->get();

        $booksArray = $books->map(function($book) {
            return [
                'id' => $book->id,
                'title' => $book->title,
                'author' => $book->author,
                'description' => $book->description,
                'price' => $book->price,
                'book_type' => $book->book_type,
                'file_type' => $book->file_type,
                'file_size' => $book->file_size,
                'category' => $book->category->name ?? null,
                'likes_count' => $book->likesCount(),
            ];
        });

        return response()->json([
            'success' => true,
            'books' => $booksArray
        ]);
    }

    public function show($id)
    {
        $book = Book::with('category')->find($id);

        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        return response()->json([
            'success' => true,
            'book' => [
                'id' => $book->id,
                'title' => $book->title,
                'author' => $book->author,
                'description' => $book->description,
                'price' => $book->price,
                'book_type' => $book->book_type,
                'file_type' => $book->file_type,
                'file_size' => $book->file_size,
                'category' => $book->category->name ?? null,
                'likes_count' => $book->likesCount()
            ]
        ]);
    }

    public function getBookWithLikes($id)
    {
        return $this->show($id);
    }

    /* =========================
       كتب المستخدم (كتبي الخاصة)
       ========================= */
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

    /* =========================
       سلة المشتريات (الكتب المدفوعة المشتراة)
       ========================= */
    public function purchasedBooks(Request $request)
    {
        $user = $request->user();

        $books = DB::table('book_user')
            ->join('books', 'book_user.book_id', '=', 'books.id')
            ->where('book_user.user_id', $user->id)
            ->where('book_user.owned', 1)
            ->where('books.book_type', 'paid')
            ->select('books.*')
            ->get();

        return response()->json([
            'success' => true,
            'books' => $books
        ]);
    }

    /* =========================
       شراء الكتب المدفوعة
       ========================= */
    public function purchaseBook(Request $request, $bookId)
    {
        $user = $request->user();
        $book = Book::find($bookId);

        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        if ($book->book_type !== 'paid') {
            return response()->json(['message' => 'هذا الكتاب مجاني، لا حاجة للشراء'], 400);
        }

        $existing = DB::table('book_user')
            ->where('book_id', $book->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && $existing->owned == 1) {
            return response()->json(['message' => 'لقد اشتريت هذا الكتاب مسبقاً'], 400);
        }

        // إضافة الكتاب في book_user مع owned = 1 و liked = 0
        DB::table('book_user')->insert([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'owned' => 1,
            'liked' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تمت عملية شراء الكتاب بنجاح وتم إضافته إلى سلة المشتريات وكتبي الخاصة',
            'book' => $book
        ]);
    }

    /* =====================================================
       Like / Unlike الكتب (Toggle API)
       ===================================================== */
    public function toggleLike(Request $request, $bookId)
    {
        $user = $request->user();
        $book = Book::find($bookId);

        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        // تحقق إذا كان المستخدم يمتلك الكتاب وحمله
        $pivot = DB::table('book_user')
            ->where('book_id', $book->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$pivot || !$pivot->owned || !$pivot->downloaded_at) {
            return response()->json([
                'message' => 'يجب أن تمتلك الكتاب وأن تكون قد حملته قبل الإعجاب'
            ], 403);
        }

        // Toggle like
        $newLiked = $pivot->liked ? 0 : 1;
        DB::table('book_user')
            ->where('book_id', $book->id)
            ->where('user_id', $user->id)
            ->update(['liked' => $newLiked]);

        // تحديث العدد الكلي للايكات
        $likesCount = DB::table('book_user')
            ->where('book_id', $book->id)
            ->where('liked', 1)
            ->count();

        $book->number_of_likes = $likesCount;
        $book->save();

        return response()->json([
            'success' => true,
            'liked' => $newLiked,
            'likes_count' => $likesCount
        ]);
    }

    /* =====================================================
       إضافة كتاب للمنصة
       ===================================================== */
    public function store(Request $request, $categoryId)
    {
        $category = Category::find($categoryId);
        if (! $category) {
            return response()->json(['message' => 'القسم غير موجود'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:50',
            'author' => 'required|string|max:30',
            'description' => 'nullable|string|max:1000',
            'price' => 'nullable|numeric|min:0',
            'book_type' => 'required|in:free,paid',
            'file' => 'nullable|file|mimes:pdf,epub|max:20480',
            'competition_book_id' => 'nullable|exists:competition_books,competition_book_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('books');
            $fileType = $file->extension();
            $fileSize = $file->getSize();
        } elseif ($request->competition_book_id) {
            $competitionBook = DB::table('competition_books')
                ->where('competition_book_id', $request->competition_book_id)
                ->first();

            if (! $competitionBook) {
                return response()->json(['message' => 'كتاب المسابقة غير موجود'], 404);
            }

            $path = $competitionBook->file_path;
            $fileType = $competitionBook->file_type;
            $fileSize = $competitionBook->file_size;
        } else {
            return response()->json([
                'message' => 'يجب رفع ملف أو اختيار كتاب من المسابقة'
            ], 422);
        }

        $book = Book::create([
            'author' => $request->author,
            'title' => $request->title,
            'description' => $request->description,
            'price' => $request->price ?? 0,
            'number_of_likes' => 0,
            'book_type' => $request->book_type,
            'file_path' => $path,
            'file_type' => $fileType,
            'file_size' => $fileSize,
            'category_id' => $categoryId
        ]);

        return response()->json([
            'message' => 'تم إضافة الكتاب بنجاح',
            'book' => $book
        ], 201);
    }

    /* =========================
       التحميل
       ========================= */
    public function generateDownloadLink(Request $request, $id)
    {
        $user = $request->user();
        $book = Book::find($id);

        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        if ($book->book_type === 'paid') {
            $pivot = DB::table('book_user')
                ->where('book_id', $book->id)
                ->where('user_id', $user->id)
                ->first();

            if (!$pivot || !$pivot->owned) {
                return response()->json(['message' => 'يجب الحصول على امتلاك الكتاب أولاً'], 403);
            }
        }

        $signedUrl = URL::temporarySignedRoute(
            'books.serveDownload',
            now()->addMinutes(10),
            ['id' => $book->id, 'userId' => $user->id]
        );

        return response()->json([
            'success' => true,
            'download_url' => $signedUrl
        ]);
    }

    public function serveDownload(Request $request, $id, $userId)
    {
        if (! $request->hasValidSignature()) {
            return response()->json(['message' => 'الرابط غير صالح'], 403);
        }

        $book = Book::find($id);
        $filePath = storage_path('app/' . $book->file_path);

        return response()->file($filePath);
    }

    /* =========================
       تحديث / حذف
       ========================= */
    public function update(Request $request, $id)
    {
        $book = Book::find($id);
        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        $book->update(
            $request->only(['title','author','description','price','book_type','category_id'])
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث معلومات الكتاب',
            'book' => $book
        ]);
    }

    public function destroy($id)
    {
        $book = Book::find($id);

        if ($book->file_path && Storage::exists($book->file_path)) {
            Storage::delete($book->file_path);
        }

        $book->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الكتاب'
        ]);
    }

    /* =========================
       🔹 API جديد: العدد الكلي للكتب للـ Admin
       ========================= */
    public function adminGetTotalBooks()
    {
        $total = Book::count();

        return response()->json([
            'success' => true,
            'total_books' => $total
        ]);
    }
}
