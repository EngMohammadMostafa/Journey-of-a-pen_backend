<?php

namespace App\Http\Controllers;

use App\Models\ReadingPlatformUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    /**
     * الحصول على قائمة جميع المستخدمين
     */
    public function getAllUsers()
    {
        // جلب جميع المستخدمين
        $users = ReadingPlatformUser::select('id', 'username', 'email', 'age', 'gender', 'user_type', 'points', 'purchases_count', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'users' => $users,
            'total_users' => $users->count()
        ]);
    }

    /**
     * تحديث بيانات مستخدم
     */
    public function updateUser(Request $request, $id)
    {
        // البحث عن المستخدم
        $user = ReadingPlatformUser::find($id);
        
        if (!$user) {
            return response()->json([
                'message' => 'المستخدم غير موجود'
            ], 404);
        }

        // التحقق من البيانات
        $validator = Validator::make($request->all(), [
            'username' => 'sometimes|string|max:20',
            'email' => 'sometimes|email|unique:reading_platform_users,email,' . $id,
            'age' => 'sometimes|integer|min:10',
            'gender' => 'sometimes|in:male,female,1,2',
            'user_type' => 'sometimes|in:1,2',
            'points' => 'sometimes|integer|min:0',
            'purchases_count' => 'sometimes|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // تحويل الجندر إذا كان رقماً
        $gender = $request->gender;
        if ($gender == 1) $gender = 'male';
        if ($gender == 2) $gender = 'female';

        // تحديث البيانات
        $user->update([
            'username' => $request->username ?? $user->username,
            'email' => $request->email ?? $user->email,
            'age' => $request->age ?? $user->age,
            'gender' => $gender ?? $user->gender,
            'user_type' => $request->user_type ?? $user->user_type,
            'points' => $request->points ?? $user->points,
            'purchases_count' => $request->purchases_count ?? $user->purchases_count
        ]);

        return response()->json([
            'message' => 'تم تحديث بيانات المستخدم بنجاح',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'age' => $user->age,
                'gender' => $user->gender,
                'user_type' => $user->user_type,
                'points' => $user->points,
                'purchases_count' => $user->purchases_count
            ]
        ]);
    }

    /**
     * حذف مستخدم
     */
    public function deleteUser($id)
    {
        // البحث عن المستخدم
        $user = ReadingPlatformUser::find($id);
        
        if (!$user) {
            return response()->json([
                'message' => 'المستخدم غير موجود'
            ], 404);
        }

        // لا يمكن حذف الأدمن الرئيسي
        if ($user->email === 'admin@readingplatform.com') {
            return response()->json([
                'message' => 'لا يمكن حذف الأدمن الرئيسي'
            ], 403);
        }

        // حذف المستخدم
        $user->delete();

        return response()->json([
            'message' => 'تم حذف المستخدم بنجاح',
            'deleted_user_id' => $id
        ]);
    }
}