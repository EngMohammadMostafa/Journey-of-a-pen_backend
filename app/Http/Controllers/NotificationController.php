<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    // عرض جميع الإشعارات (للمستخدمين)
    public function index()
    {
        return response()->json(
            Notification::latest()->get()
        );
    }

    // إضافة إشعار (Admin فقط)
    public function store(Request $request)
    {
        $user = Auth::user();

        // التحقق أنه اداري
        if ($user->user_type != 2) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'title'   => 'required|string|max:20',
            'content' => 'required|string|max:255',
        ]);

        Notification::create([
            'title'   => $request->title,
            'content' => $request->content,
            'user_id' => $user->user_id // الاسم الصحيح للـ PK عندك
        ]);

        return response()->json([
            'message' => 'Notification created successfully'
        ]);
    }

    // حذف إشعار (Admin فقط)
    public function destroy($id)
    {
        $user = Auth::user();

        if ($user->user_type != 2) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        Notification::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Notification deleted successfully'
        ]);
    }
}
