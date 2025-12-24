<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * عرض جميع الإشعارات (للمستخدمين)
     */
    public function index()
    {
        return response()->json(
            Notification::latest()->get()
        );
    }

    /**
     * إضافة إشعار (Admin فقط)
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // التحقق أنه اداري
        if ($user->user_type != 2) {
            return response()->json(['message' => 'غير مصرح لك'], 403);
        }

        // التحقق من صحة البيانات
        $request->validate([
            'title'   => 'required|string|max:20',
            'content' => 'required|string|max:255',
        ], [
            'title.required' => 'العنوان مطلوب',
            'title.string'   => 'العنوان يجب أن يكون نصًا',
            'title.max'      => 'العنوان يجب ألا يتجاوز 20 حرفًا',
            'content.required' => 'المحتوى مطلوب',
            'content.string'   => 'المحتوى يجب أن يكون نصًا',
            'content.max'      => 'المحتوى يجب ألا يتجاوز 255 حرفًا',
        ]);

        // إنشاء الإشعار
        Notification::create([
            'title'   => $request->title,
            'content' => $request->content,
            'user_id' => $user->id
        ]);

        // رسالة بالعربي
        return response()->json([
            'message' => 'تم إضافة الإشعار بنجاح'
        ]);
    }

    /**
     * حذف إشعار (Admin فقط)
     */
    public function destroy($id)
    {
        $user = Auth::user();

        if ($user->user_type != 2) {
            return response()->json(['message' => 'غير مصرح لك'], 403);
        }

        Notification::findOrFail($id)->delete();

        return response()->json([
            'message' => 'تم حذف الإشعار بنجاح'
        ]);
    }
}
