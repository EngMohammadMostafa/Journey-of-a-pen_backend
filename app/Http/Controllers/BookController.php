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

    /* =====================================================
       🔴 إضافة كتاب للمنصة
       ===================================================== */
    public function store(Request $request, $categoryId)
    {
        $category = Category::find($categoryId);
        if (! $category) {
            return response()->json(['message' => 'القسم غير موجود'], 404);
        }

        // Validation: الملف اختياري + يمكن اختيار كتاب مسابقة
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

        // تحديد مصدر الملف
        if ($request->hasFile('file')) {
            // حالة رفع ملف جديد من الأدمن
            $file = $request->file('file');
            $path = $file->store('books');
            $fileType = $file->extension();
            $fileSize = $file->getSize();
        } elseif ($request->competition_book_id) {
            // حالة استخدام كتاب من المسابقة
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

        // إنشاء الكتاب في المنصة
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
