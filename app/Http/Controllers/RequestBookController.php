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
     * عرض نموذج تقديم طلب الكتاب للمستخدم (ليس ضروري في API)
     */
    public function create()
    {
        return response()->json([
            'message' => 'هذه النقطة مخصصة للـ Web وليس API'
        ]);
    }

    /**
     * تخزين طلب الكتاب المرسل من المستخدم
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:50',
            'description' => 'required|string|max:255',
            'book_type' => 'required|in:free,paid',
            'price' => 'nullable|integer|min:0',
            'file' => 'required|file|mimes:pdf|max:20480', // PDF فقط
        ], [
            'title.required' => 'الرجاء إدخال عنوان الكتاب',
            'description.required' => 'الرجاء إدخال وصف الكتاب',
            'book_type.required' => 'الرجاء اختيار نوع الكتاب',
            'file.required' => 'الرجاء إرفاق ملف الكتاب',
            'file.mimes' => 'الرجاء إرفاق ملف بصيغة PDF فقط',
        ]);

        if ($request->book_type === 'paid' && is_null($request->price)) {
            return response()->json([
                'status' => 'error',
                'message' => 'الرجاء إدخال سعر الكتاب لأنه مدفوع'
            ], 422);
        }

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'فشل التحقق من البيانات',
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->file('file');
        $file_path = $file->store('request_books', 'public');

        $requestBook = RequestBook::create([
            'user_id' => Auth::id(),
            'title' => $request->title,
            'description' => $request->description,
            'book_type' => $request->book_type,
            'price' => $request->price ?? 0,
            'file_path' => $file_path,
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'تم إرسال طلب إضافة الكتاب بنجاح، بانتظار مراجعة الإدارة.',
            'request_book' => $requestBook
        ], 201);
    }

    /**
     * عرض جميع الطلبات للأدمن
     */
    public function index()
    {
        $requests = RequestBook::orderBy('created_at', 'desc')->get();
        $categories = Category::all();

        return response()->json([
            'status' => 'success',
            'requests' => $requests,
            'categories' => $categories
        ]);
    }

    /**
     * قبول الطلب من قبل الأدمن
     */
    public function accept(Request $request, $id)
    {
        $requestBook = RequestBook::findOrFail($id);

        if (!$request->category_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'الرجاء اختيار قسم للكتاب.'
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

        $requestBook->update([
            'status' => 'accepted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'تمت الموافقة على الطلب وإضافة الكتاب إلى المنصة.',
            'book' => $book
        ]);
    }

    /**
     * رفض الطلب من قبل الأدمن
     */
    public function reject($id)
    {
        $requestBook = RequestBook::findOrFail($id);

        if ($requestBook->file_path && Storage::disk('public')->exists($requestBook->file_path)) {
            Storage::disk('public')->delete($requestBook->file_path);
        }

        $requestBook->update([
            'status' => 'rejected',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'تم رفض الطلب.'
        ]);
    }

    /**
     * تحميل ملف الكتاب للأدمن لمراجعته قبل اتخاذ القرار
     */
    public function downloadFile($id)
    {
        $requestBook = RequestBook::findOrFail($id);

        $filePath = $requestBook->file_path;

        if (!$filePath || !Storage::disk('public')->exists($filePath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'الملف غير موجود'
            ], 404);
        }

        return response()->download(storage_path("app/public/{$filePath}"));
    }
}
