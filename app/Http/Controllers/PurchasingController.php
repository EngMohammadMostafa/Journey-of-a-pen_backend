<?php

namespace App\Http\Controllers;

use App\Models\Purchasing;
use App\Models\ReadingPlatformUser;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PurchasingController extends Controller
{
    // Admin + user: عرض كل عمليات الشراء (admin) أو عمليات المستخدم الحالية
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->user_type == 2) {
            $purchases = Purchasing::with('users','books')->get();
        } else {
            $purchases = $user->purchases()->with('books')->get();
        }
        return response()->json(['purchases'=>$purchases]);
    }

    // إنشاء عملية شراء وربطها بالمستخدم والكتب
    // هذا يكون بعد تأكيد الدفع (أي Stripe سيرد النجاح ثم نستدعي هذه الدالة)
    public function createPurchase(Request $request)
    {
        // body: { book_ids: [1,2], purchase_date: '2025-11-01' }
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'book_ids' => 'required|array|min:1',
            'book_ids.*' => 'integer|exists:books,id',
            'purchase_date' => 'sometimes|date'
        ]);

        if ($validator->fails()) return response()->json(['errors'=>$validator->errors()], 422);

        $purchase = Purchasing::create([
            'purchase_date' => $request->purchase_date ?? now()->toDateString()
        ]);

        // اربط المستخدم بالشراء
        $user->purchases()->attach($purchase->id);

        // اربط الكتب بالشراء عبر book_user pivot (اذا تصممتم انه الربط يكون عبر book_user)
        // حسب تصميمك: المستخدم مرتبط بالكتب عبر book_user، لذا نضع هنا علاقة ملكية/امتلاك:
        foreach ($request->book_ids as $bookId) {
            // إذا كنتم تعتمدون book_user لحفظ ملكية/like، سنضيف record لامتلاك الكتاب
            // إذا كنتم تفرقون بين الاعجاب والامتلاك استخدموا حقل آخر في pivot
            $user->books()->syncWithoutDetaching([$bookId]);
        }

        // تحديث عداد المشتريات للمستخدم
        $user->increment('purchases_count', count($request->book_ids));

        return response()->json(['message'=>'تم إنشاء عملية الشراء','purchase'=>$purchase], 201);
    }

    // جلب تفاصيل عملية شراء محددة
    public function show($id)
    {
        $purchase = Purchasing::with('users','books')->find($id);
        if (!$purchase) return response()->json(['message'=>'الشراء غير موجود'], 404);
        return response()->json(['purchase'=>$purchase]);
    }
}
