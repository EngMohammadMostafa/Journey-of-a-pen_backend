<?php

// حل CORS السريع - أضيفي هذا الكود في الأعلى
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// التعامل مع طلبات OPTIONS - بشكل آمن
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\QuoteController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// 🔓 routes لا تتطلب توكن (مفتوحة)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);   // التسجيل
    Route::post('/login', [AuthController::class, 'login']);         // الدخول 
});

// 🔐 routes تتطلب توكن (محمية)
Route::middleware('auth:sanctum')->group(function () {
    // الخروج
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    
    // بيانات المستخدم
    Route::prefix('users')->group(function () {
        Route::get('/me', [UserController::class, 'getCurrentUser']);      // الحصول على بياناتي
        Route::put('/me', [UserController::class, 'updateCurrentUser']);   // تحديث بياناتي
    });

    // 📖 الاقتباسات (لجميع المستخدمين المسجلين)
    Route::get('/quotes', [QuoteController::class, 'index']);
    Route::post('/quotes', [QuoteController::class, 'store']);
    // ⚠️ لاحظي: Route::delete تم نقله للأدمن فقط
});

// 🔐 routes الإداري (تتطلب توكن + صلاحية أدمن)
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    
    // إدارة المستخدمين
    Route::prefix('users')->group(function () {
        Route::get('/', [AdminController::class, 'getAllUsers']);           // الحصول على جميع المستخدمين
        Route::put('/{id}', [AdminController::class, 'updateUser']);       // تحديث مستخدم
        Route::delete('/{id}', [AdminController::class, 'deleteUser']);    // حذف مستخدم
    });
    
    // 🗑️ إدارة الاقتباسات - للإدمن فقط
    Route::delete('/quotes/{id}', [QuoteController::class, 'destroy']);
});

// 🌐 route أساسي للتحقق
Route::get('/', function () {
    return response()->json([
        'message' => 'مرحباً بكم في منصة القراءة التحفيزية',
        'version' => '1.0'
    ]);
});