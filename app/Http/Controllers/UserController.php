<?php

namespace App\Http\Controllers;

use App\Models\ReadingPlatformUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * الحصول على بيانات المستخدم الحالي (مع كتب البروفايل)
     *
     * Route: GET /api/users/me
     * Middleware: auth:sanctum
     *
     * الاستجابة تتضمن:
     * - user.points => المجموع الكلي لنقاط المستخدم
     * - user.books  => بيانات الكتب مع معلومات الـ pivot (liked, owned, downloaded_at)
     */
    public function getCurrentUser(Request $request)
    {
        // المستخدم المصادق
        $user = $request->user();

        // نحمل علاقة الكتب مع حقول pivot: liked, owned, downloaded_at
        // نختار أعمدة مهمة من جدول الكتب لتخفيف حجم الاستجابة
        $user->load(['books' => function($q) {
            $q->select('books.id','title','author','file_path','file_type','file_size','category_id');
        }]);

        // نُحوّل كل كتاب لإضافة معلومات مفيدة للواجهة
        $books = $user->books->map(function($book) use ($user) {
            // لا نُعيد رابط التخزين العام لأسباب أمنية بشكل افتراضي.
            // بدلاً من ذلك نُعيد endpoint لاستدعاء توليد رابط تحميل مؤقت (signed URL).
            // الواجهة ستنادي هذا endpoint (POST /api/books/{id}/download) للحصول على download_url.
            return [
                'id' => $book->id,
                'title' => $book->title,
                'author' => $book->author,
                'file_type' => $book->file_type,
                'file_size' => $book->file_size,
                // pivot info
                'liked' => (bool) $book->pivot->liked,
                'owned' => (bool) $book->pivot->owned,
                'downloaded_at' => $book->pivot->downloaded_at,
                // endpoint آمن يقوم بتوليد رابط التحميل المؤقت عند الطلب
                // الواجهة تستخدمه (POST) ثم تحصل على download_url مؤقت وتفتح/تحمّل الملف
                'download_endpoint' => url("/api/books/{$book->id}/download")
            ];
        });

        // نُعيد بيانات المستخدم مع الكتب
        return response()->json([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'age' => $user->age,
                'gender' => $user->gender,
                'user_type' => $user->user_type,
                'points' => $user->points,           // <-- المجموع الكلي للنقاط
                'purchases_count' => $user->purchases_count,
                'books' => $books // الكتب في بروفايل المستخدم
            ]
        ]);
    }

    /**
     * إعادة عدد النقاط الكلي للمستخدم الحالي فقط (نقطة نهاية بسيط ومباشر)
     *
     * Route suggestion: GET /api/users/points
     * Middleware: auth:sanctum
     *
     * الاستجابة:
     * { "total_points": 120 }
     *
     * ملاحظة: لا تُعيد أي بيانات حسّاسة أخرى، وتُستخدم لعرض النقاط في صفحة البروفايل.
     */
    public function getTotalPoints(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'total_points' => (int) $user->points
        ]);
    }

    // بقية الدوال كما كانت (updateCurrentUser و changePassword) — لا تغيير عليهما هنا

    /**
     * تحديث بيانات المستخدم الحالي (username, age, gender)
     *
     * Route: PUT /api/users/me
     * Middleware: auth:sanctum
     */
    public function updateCurrentUser(Request $request)
    {
        $user = $request->user();

        // التحقق من البيانات
        $validator = Validator::make($request->all(), [
            'username' => 'sometimes|string|max:20',
            'age' => 'sometimes|integer|min:10',
            'gender' => 'sometimes|in:male,female,1,2'
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
            'age' => $request->age ?? $user->age,
            'gender' => $gender ?? $user->gender
        ]);

        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'age' => $user->age,
            'gender' => $user->gender,
            'message' => 'تم تحديث البيانات بنجاح'
        ]);
    }

    /**
     * تغيير كلمة المرور للمستخدم الحالي
     *
     * Route: POST /api/users/me/change-password
     * Middleware: auth:sanctum
     *
     * متطلبات كلمة المرور: طول 8+، حرف كبير، حرف صغير، رقم، ورمز خاص
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        // التحقق من البيانات
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            'new_password_confirmation' => 'required|same:new_password'
        ], [
            'new_password.regex' => 'كلمة المرور يجب أن تحتوي على حرف كبير، حرف صغير، رقم، ورمز خاص.',
            'new_password_confirmation.same' => 'كلمة المرور وتأكيدها غير متطابقين.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // التحقق من كلمة المرور الحالية
        if (!\Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'errors' => [
                    'current_password' => ['كلمة المرور الحالية غير صحيحة']
                ]
            ], 422);
        }

        // تحديث كلمة المرور
        $user->update([
            'password' => \Illuminate\Support\Facades\Hash::make($request->new_password)
        ]);

        return response()->json([
            'message' => 'تم تغيير كلمة المرور بنجاح'
        ]);
    }
}
