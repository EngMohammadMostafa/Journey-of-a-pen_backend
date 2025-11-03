<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Category;
use App\Models\License;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BookController extends Controller
{
    /**
     * 🟢 عرض جميع الأقسام والكتب فيها
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
     * 🟢 عرض كتب قسم محدد
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
     * 🟢 تحميل الكتاب (يتم التحقق من الشراء إذا كان مدفوع)
     */
    public function downloadBook(Request $request, $bookId)
    {
        $user = Auth::user();
        $book = Book::find($bookId);

        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        // 🧾 التحقق من الشراء إن كان الكتاب مدفوع
        if ($book->book_type === 'paid' && !$this->checkPurchase($user->id, $book->id)) {
            return response()->json([
                'message' => 'يجب شراء هذا الكتاب أولاً قبل تحميله'
            ], 403);
        }

        // 🗂️ توليد رابط تحميل مؤقت (الملف يكون مخزن في storage/app/books/)
        if (!Storage::disk('local')->exists($book->file_path)) {
            return response()->json(['message' => 'ملف الكتاب غير موجود في الخادم'], 404);
        }

        // رابط مؤقت صالح لمدة 10 دقائق
        $url = Storage::temporaryUrl($book->file_path, now()->addMinutes(10));

        return response()->json([
            'success' => true,
            'message' => 'تم السماح بتحميل الكتاب',
            'download_url' => $url
        ]);
    }

    /**
     * 🔒 التحقق من شراء الكتاب
     */
    private function checkPurchase($userId, $bookId)
    {
        return Purchase::where('user_id', $userId)
            ->where('book_id', $bookId)
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * ❤️ تسجيل إعجاب بالكتاب
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
}
