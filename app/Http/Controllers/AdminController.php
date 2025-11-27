<?php

namespace App\Http\Controllers;

use App\Models\ReadingPlatformUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    /**
     * الحصول على قائمة جميع المستخدمين
     * يمكن للأدمن فقط الوصول لهذا endpoint
     */
    public function getAllUsers()
    {
        $users = ReadingPlatformUser::select(
            'id', 'username', 'email', 'age', 'gender', 'user_type', 'points', 'purchases_count', 'created_at'
        )->orderBy('created_at', 'desc')->get();

        return response()->json([
            'users' => $users,
            'total_users' => $users->count()
        ]);
    }

    /**
     * إنشاء مستخدم جديد بواسطة الأدمن مع تطبيق قيود كلمة المرور مثل AuthController
     * ملاحظات:
     * - المستخدم الجديد دائماً user_type = 1 (مستخدم عادي)
     * - age و gender إلزامية لتجنب أخطاء قاعدة البيانات
     * - password يجب أن يكون قوي ويحتوي على: حرف كبير، حرف صغير، رقم، ورمز خاص
     */
    public function createUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:20',
            'email' => 'required|email|unique:reading_platform_users,email',
            'password' => 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            'password_confirmation' => 'required|same:password', // يجب إرسال password_confirmation
            'age' => 'required|integer|min:10',
            'gender' => 'required|in:male,female'
        ], [
            'password.regex' => 'كلمة المرور يجب أن تحتوي على حرف كبير، حرف صغير، رقم، ورمز خاص.',
            'password_confirmation.same' => 'كلمة المرور وتأكيدها غير متطابقين.'
        ]);

        // التحقق من صحة البيانات
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // إنشاء المستخدم الجديد كـ مستخدم عادي
        $user = ReadingPlatformUser::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'age' => $request->age,
            'gender' => $request->gender,
            'user_type' => 1, // دائماً مستخدم عادي
            'points' => 0,
            'purchases_count' => 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء المستخدم بنجاح',
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
        ], 201);
    }

    /**
     * تحديث بيانات مستخدم موجود
     * يمكن أيضاً تحديث كلمة المرور بنفس القيود
     */
    public function updateUser(Request $request, $id)
    {
        $user = ReadingPlatformUser::find($id);
        if (!$user) {
            return response()->json(['message' => 'المستخدم غير موجود'], 404);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'sometimes|string|max:20',
            'email' => 'sometimes|email|unique:reading_platform_users,email,' . $id,
            'age' => 'sometimes|integer|min:10',
            'gender' => 'sometimes|in:male,female',
            'user_type' => 'sometimes|in:1,2',
            'points' => 'sometimes|integer|min:0',
            'purchases_count' => 'sometimes|integer|min:0',
            // تحديث كلمة المرور بنفس القيود
            'password' => 'sometimes|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            'password_confirmation' => 'sometimes|required_with:password|same:password'
        ], [
            'password.regex' => 'كلمة المرور يجب أن تحتوي على حرف كبير، حرف صغير، رقم، ورمز خاص.',
            'password_confirmation.same' => 'كلمة المرور وتأكيدها غير متطابقين.'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // تحديث البيانات مع الاحتفاظ بالقيم القديمة إذا لم تُرسل جديدة
        $user->update([
            'username' => $request->username ?? $user->username,
            'email' => $request->email ?? $user->email,
            'age' => $request->age ?? $user->age,
            'gender' => $request->gender ?? $user->gender,
            'user_type' => $request->user_type ?? $user->user_type,
            'points' => $request->points ?? $user->points,
            'purchases_count' => $request->purchases_count ?? $user->purchases_count,
            'password' => $request->password ? Hash::make($request->password) : $user->password
        ]);

        return response()->json([
            'message' => 'تم تحديث بيانات المستخدم بنجاح',
            'user' => $user
        ]);
    }

    /**
     * حذف مستخدم
     * لا يمكن حذف الأدمن الرئيسي
     */
    public function deleteUser($id)
    {
        $user = ReadingPlatformUser::find($id);
        if (!$user) {
            return response()->json(['message' => 'المستخدم غير موجود'], 404);
        }

        // لا يمكن حذف الأدمن الرئيسي
        if ($user->user_type == 2) {
            return response()->json(['message' => 'لا يمكن حذف الأدمن الرئيسي'], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'تم حذف المستخدم بنجاح',
            'deleted_user_id' => $id
        ]);
    }
}
