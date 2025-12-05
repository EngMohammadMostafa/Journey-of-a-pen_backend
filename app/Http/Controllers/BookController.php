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

        $likesCount = $book->likesCount();

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
                'likes_count' => $likesCount
            ]
        ]);
    }

    
    public function getBookWithLikes($id)
    {
        $book = Book::with('category')->find($id);

        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        $likesCount = $book->likesCount();

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
                'likes_count' => $likesCount
            ]
        ]);
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
            'file' => 'required|file|mimes:pdf,epub|max:20480',
           
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $path = $file->store('books');

        $book = Book::create([
            'author' => $request->author,
            'title' => $request->title,
            'description' => $request->description,
            'price' => $request->price ?? 0,
            'number_of_likes' => 0,
           
            'book_type' => $request->book_type,
            'file_path' => $path,
            'file_type' => $file->extension(),
            'file_size' => $file->getSize(),
            'category_id' => $categoryId
        ]);

        return response()->json(['message' => 'تم إضافة الكتاب بنجاح', 'book' => $book], 201);
    }

    
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

            if (!$pivot->downloaded_at) {
                DB::table('book_user')
                    ->where('book_id', $book->id)
                    ->where('user_id', $user->id)
                    ->update(['downloaded_at' => now()]);
            }
        } else {
            
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

        if (!$book->file_path || !Storage::disk('local')->exists($book->file_path)) {
            return response()->json(['message' => 'ملف الكتاب غير موجود على الخادم'], 404);
        }

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

    
    public function update(Request $request, $id)
    {
        $book = Book::find($id);
        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        
        $data = $request->only(['title','author','description','price','book_type','category_id']); 
        $book->update($data);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث معلومات الكتاب',
            'book' => $book
        ]);
    }

    
    public function destroy($id)
    {
        $book = Book::find($id);
        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

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
