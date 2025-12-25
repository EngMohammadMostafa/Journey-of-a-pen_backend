<?php

namespace App\Http\Controllers;

use App\Models\RequestBook;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class RequestBookController extends Controller
{
    /**
     * تخزين طلب الكتاب
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:50',
            'description' => 'required|string|max:255',
            'book_type' => 'required|in:free,paid',
            'price' => 'nullable|integer|min:0',
            'file' => 'required|file|mimes:pdf|max:20480',
        ]);

        if ($request->book_type === 'paid' && $request->price === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'سعر الكتاب مطلوب لأنه مدفوع'
            ], 422);
        }

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // ✅ تخزين الملف داخل public
        $file = $request->file('file');
        $filePath = $file->store('request_books', 'public');

        $requestBook = RequestBook::create([
            'user_id' => Auth::id(),
            'title' => $request->title,
            'description' => $request->description,
            'book_type' => $request->book_type,
            'price' => $request->price ?? 0,
            'file_path' => $filePath,
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'request_book' => $requestBook
        ], 201);
    }

    /**
     * عرض الطلبات للأدمن
     */
    public function index()
    {
        return response()->json([
            'requests' => RequestBook::latest()->get(),
            'categories' => Category::all()
        ]);
    }

    /**
     * قبول الطلب
     */
    public function accept(Request $request, $id)
    {
        $requestBook = RequestBook::findOrFail($id);

        if (!$request->category_id) {
            return response()->json([
                'message' => 'يجب اختيار قسم'
            ], 422);
        }

        $book = Book::create([
            'category_id' => $request->category_id,
            'price' => $requestBook->price,
            'author' => $requestBook->user->username,
            'title' => $requestBook->title,
            'description' => $requestBook->description,
            'book_type' => $requestBook->book_type,
            'file_path' => $requestBook->file_path,
            'file_type' => $requestBook->file_type,
            'file_size' => $requestBook->file_size,
        ]);

        $requestBook->update(['status' => 'accepted']);

        return response()->json([
            'message' => 'تم قبول الطلب',
            'book' => $book
        ]);
    }

    /**
     * رفض الطلب
     */
    public function reject($id)
    {
        $requestBook = RequestBook::findOrFail($id);

        // ✅ حذف الملف من public
        if ($requestBook->file_path && Storage::disk('public')->exists($requestBook->file_path)) {
            Storage::disk('public')->delete($requestBook->file_path);
        }

        $requestBook->update(['status' => 'rejected']);

        return response()->json([
            'message' => 'تم رفض الطلب'
        ]);
    }

    /**
     * تحميل ملف الطلب (للأدمن)
     */
    public function downloadFile($id)
    {
        $requestBook = RequestBook::findOrFail($id);

        if (
            !$requestBook->file_path ||
            !Storage::disk('public')->exists($requestBook->file_path)
        ) {
            return response()->json([
                'message' => 'الملف غير موجود'
            ], 404);
        }

        // ✅ المسار الصحيح
        return response()->download(
            storage_path('app/public/' . $requestBook->file_path)
        );
    }
}
