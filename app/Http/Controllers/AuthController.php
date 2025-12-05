<?php

namespace App\Http\Controllers;

use App\Models\ReadingPlatformUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    
    public function register(Request $request)
    {
        
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:20',
            'email' => 'required|email|unique:reading_platform_users',
            'password' => 'required|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            'password_confirmation' => 'required|same:password',
            'age' => 'required|integer|min:10',
            'gender' => ['required', Rule::in(['male', 'female'])]
        ], [
            'password.regex' => 'كلمة المرور يجب أن تحتوي على حرف كبير، حرف صغير، رقم، ورمز خاص.',
            'password_confirmation.same' => 'كلمة المرور وتأكيدها غير متطابقين.'
        ]);

        
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        
        $user = ReadingPlatformUser::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'age' => $request->age,
            'gender' => $request->gender,
            'user_type' => 1, 
            'points' => 0,
            'purchases_count' => 0
        ]);

        

        return response()->json([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'age' => $user->age,
                'gender' => $user->gender,
                'user_type' => $user->user_type,
                'points' => $user->points,
                'purchases_count' => $user->purchases_count
            ],
            'message' => 'تم إنشاء الحساب بنجاح'
        ], 201);
    }

   
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

       
        $user = ReadingPlatformUser::where('email', $request->email)->first();

        
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'بيانات الدخول غير صحيحة'
            ], 401);
        }

        
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

    
    public function logout(Request $request)
    {
        
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح'
        ]);
    }
}
