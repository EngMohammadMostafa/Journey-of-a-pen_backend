<?php

namespace App\Http\Controllers;

use App\Models\ReadingPlatformUser;
use App\Models\Book;
use App\Models\Question;
use App\Models\Competition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    /**
     * عرض جميع المستخدمين
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
     * عرض مستخدم معين حسب الـ ID
     */
    public function getUserById($id)
    {
        $user = ReadingPlatformUser::select(
            'id', 'username', 'email', 'age', 'gender', 'user_type', 'points', 'purchases_count', 'created_at'
        )->find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم غير موجود'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'user' => $user
        ]);
    }

    /**
     * إنشاء مستخدم جديد
     */
    public function createUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:20',
            'email' => 'required|email|unique:reading_platform_users,email',
            'password' => 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            'password_confirmation' => 'required|same:password', 
            'age' => 'required|integer|min:10',
            'gender' => 'required|in:male,female'
        ], [
            'password.regex' => 'كلمة المرور يجب أن تحتوي على حرف كبير، حرف صغير، رقم، ورمز خاص.',
            'password_confirmation.same' => 'كلمة المرور وتأكيدها غير متطابقين.'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = ReadingPlatformUser::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'age' => $request->age,
            'gender' => $request->gender,
            'user_type' => 1, // افتراضي مستخدم عادي
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
     * تعديل بيانات مستخدم
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
            'password' => 'sometimes|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            'password_confirmation' => 'sometimes|required_with:password|same:password'
        ], [
            'password.regex' => 'كلمة المرور يجب أن تحتوي على حرف كبير، حرف صغير، رقم، ورمز خاص.',
            'password_confirmation.same' => 'كلمة المرور وتأكيدها غير متطابقين.'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

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
     */
    public function deleteUser($id)
    {
        $user = ReadingPlatformUser::find($id);
        if (!$user) {
            return response()->json(['message' => 'المستخدم غير موجود'], 404);
        }

        // منع حذف الأدمن الرئيسي
        if ($user->user_type == 2) {
            return response()->json(['message' => 'لا يمكن حذف الأدمن الرئيسي'], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'تم حذف المستخدم بنجاح',
            'deleted_user_id' => $id
        ]);
    }

    // =========================
    // 🔹 دوال إحصاءات عامة للـ Admin
    // =========================

    /**
     * عدد الكتب الكلي
     */
    public function totalBooks()
    {
        $total = Book::count();

        return response()->json([
            'success' => true,
            'total_books' => $total
        ]);
    }

    /**
     * عدد الأسئلة الكلي
     */
    public function totalQuestions()
    {
        $total = Question::count();

        return response()->json([
            'success' => true,
            'total_questions' => $total
        ]);
    }

    /**
     * عدد المسابقات الكلي
     */
    public function totalCompetitions()
    {
        $total = Competition::count();

        return response()->json([
            'success' => true,
            'total_competitions' => $total
        ]);
    }
}
