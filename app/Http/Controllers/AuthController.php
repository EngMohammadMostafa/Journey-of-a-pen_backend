<?php

namespace App\Http\Controllers;

use App\Models\ReadingPlatformUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * تسجيل مستخدم جديد
     */
    public function register(Request $request)
    {
        // التحقق من البيانات
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:20',
            'email' => 'required|email|unique:reading_platform_users',
            'password' => 'required|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            'password_confirmation' => 'required|same:password', // تأكيد كلمة المرور
            'age' => 'required|integer|min:10',
            'gender' => ['required', Rule::in(['male', 'female'])]
        ], [
            'password.regex' => 'كلمة المرور يجب أن تحتوي على حرف كبير، حرف صغير، رقم، ورمز خاص.',
            'password_confirmation.same' => 'كلمة المرور وتأكيدها غير متطابقين.'
        ]);

        // إذا كان هناك أخطاء
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // إنشاء المستخدم
        $user = ReadingPlatformUser::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'age' => $request->age,
            'gender' => $request->gender,
            'user_type' => 1, // مستخدم عادي
            'points' => 0,
            'purchases_count' => 0
        ]);

        // إنشاء التوكن
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
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
     * تسجيل الدخول
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

       

        // البحث عن المستخدم
        $user = ReadingPlatformUser::where('email', $request->email)->first();

        // التحقق من كلمة المرور
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'بيانات الدخول غير صحيحة'
            ], 401);
        }

        // إنشاء التوكن
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
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
     * تسجيل الخروج
     */
    public function logout(Request $request)
    {
        // حذف التوكن الحالي
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح'
        ]);
    }
}