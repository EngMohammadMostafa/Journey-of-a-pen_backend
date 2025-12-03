<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuoteController extends Controller
{
    // 📖 استعراض جميع الاقتباسات
    public function index()
    {
        $quotes = Quote::latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'تم جلب الاقتباسات بنجاح',
            'quotes' => $quotes,
            'count' => $quotes->count()
        ], 200);
    }

    // ➕ نشر اقتباس جديد
    public function store(Request $request)
    {
        // ✅ التحقق من صحة البيانات
        $request->validate([
            'text' => 'required|string|max:255',       // النص لا يزيد عن 255 حرف
            'book_name' => 'required|string|max:50'    // تم تعديل الحد الأقصى من 20 إلى 50 حرف
        ]);

        // إنشاء الاقتباس وربطه بالمستخدم الحالي
        $quote = Quote::create([
            'text' => $request->text,
            'book_name' => $request->book_name,
            'user_id' => Auth::id()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم نشر الاقتباس بنجاح',
            'quote' => $quote
        ], 201);
    }

    // 🗑️ حذف اقتباس (للإدمن فقط)
    public function destroy($id)
    {
        // التحقق من صلاحية المستخدم (أدمن فقط)
        if (Auth::user()->user_type !== 2) {
            return response()->json([
                'success' => false,
                'message' => 'غير مسموح - تحتاج صلاحيات أدمن'
            ], 403);
        }

        $quote = Quote::find($id);
        
        // التحقق من وجود الاقتباس
        if (!$quote) {
            return response()->json([
                'success' => false,
                'message' => 'الاقتباس غير موجود'
            ], 404);
        }

        // حذف الاقتباس
        $quote->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الاقتباس بنجاح'
        ], 200);
    }
}
