<?php

namespace App\Http\Controllers;

use App\Models\RequestBook;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class RequestBookController extends Controller
{
    /**
     * عرض نموذج تقديم طلب الكتاب للمستخدم
     */
    public function create()
    {
        return view('request_books.create');
    }

    /**
     * تخزين طلب الكتاب المرسل من المستخدم
     */
    public function store(Request $request)
    {
        // التحقق من البيانات + رسائل عربية
        $request->validate([
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

        // حفظ الملف مؤقتاً
        $file = $request->file('file');
        $file_path = $file->store('request_books', 'public');

        // إنشاء طلب الكتاب
        RequestBook::create([
            'user_id' => Auth::id(),
            'title' => $request->title,
            'description' => $request->description,
            'book_type' => $request->book_type,
            'price' => $request->price ?? 0,
            'file_path' => $file_path,
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
            'status' => 'pending', // الحالة الافتراضية
        ]);

        return redirect()->back()->with('success', 'تم إرسال طلب إضافة الكتاب بنجاح، بانتظار مراجعة الإدارة.');
    }

    /**
     * عرض جميع الطلبات للأدمن
     */
    public function index()
    {
        // عرض جميع الطلبات (pending / accepted / rejected)
        $requests = RequestBook::orderBy('created_at', 'desc')->get();
        $categories = Category::all();

        return view('request_books.index', compact('requests', 'categories'));
    }

    /**
     * قبول الطلب من قبل الأدمن
     */
    public function accept(Request $request, $id)
    {
        $requestBook = RequestBook::findOrFail($id);

        // التحقق من اختيار القسم
        if (!$request->category_id) {
            return redirect()->back()->with('error', 'الرجاء اختيار قسم للكتاب.');
        }

        // إنشاء الكتاب في جدول Book
        Book::create([
            'category_id' => $request->category_id,
            'price' => $requestBook->price,
            'author' => $requestBook->user->username, // اسم المستخدم هو المؤلف
            'title' => $requestBook->title,
            'description' => $requestBook->description,
            'book_type' => $requestBook->book_type,
            'file_path' => $requestBook->file_path,
            'file_type' => $requestBook->file_type,
            'file_size' => $requestBook->file_size,
        ]);

        // تحديث حالة الطلب فقط (بدون حذف)
        $requestBook->update([
            'status' => 'accepted',
        ]);

        return redirect()->back()->with('success', 'تمت الموافقة على الطلب وإضافة الكتاب إلى المنصة.');
    }

    /**
     * رفض الطلب من قبل الأدمن
     * ❗ لا نحذف الطلب من قاعدة البيانات
     * ❗ فقط نغيّر الحالة إلى rejected
     */
    public function reject($id)
    {
        $requestBook = RequestBook::findOrFail($id);

        // حذف الملف المرفوع (اختياري)
        if ($requestBook->file_path && Storage::disk('public')->exists($requestBook->file_path)) {
            Storage::disk('public')->delete($requestBook->file_path);
        }

        // تحديث حالة الطلب إلى rejected (بدون حذف السجل)
        $requestBook->update([
            'status' => 'rejected',
        ]);

        return redirect()->back()->with('success', 'تم رفض الطلب.');
    }
}
