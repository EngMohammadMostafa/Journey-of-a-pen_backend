<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\AnswerController;
use App\Http\Controllers\UserBookAnswerController;
use App\Http\Controllers\RewardController;
use App\Http\Controllers\RepointController;
use App\Http\Controllers\CategoryController;

/*
|-------------------------------------------------------------------------- 
| API Routes
|-------------------------------------------------------------------------- 
| جميع المسارات المتعلقة بالمشروع: auth, users, books, questions, admin, rewards ...
| تم تقسيم المسارات حسب الحاجة: عامة، auth، admin.
*/

/* ------------------------------
   مسارات مفتوحة (بدون auth)
-------------------------------- */
Route::get('/', function () {
    return response()->json(['message' => 'API is running']);
});

// مسارات التسجيل والدخول
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']); // تسجيل مستخدم جديد
    Route::post('/login', [AuthController::class, 'login']);       // تسجيل الدخول
});

// الأقسام متاحة للعامة
Route::get('/categories', [CategoryController::class, 'index']);  // كل الأقسام
Route::get('/categories/{id}', [CategoryController::class, 'show']); // تفاصيل قسم

/* ------------------------------
   مسارات تتطلب مصادقة (auth:sanctum)
-------------------------------- */
Route::middleware(['auth:sanctum'])->group(function () {

    // تسجيل الخروج
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // بيانات المستخدم
    Route::get('/users/me', [UserController::class, 'getCurrentUser']); // معلومات المستخدم الحالي
    Route::put('/users/me', [UserController::class, 'updateCurrentUser']); // تعديل بيانات المستخدم
    Route::post('/users/me/change-password', [UserController::class, 'changePassword']); // تغيير كلمة المرور

    // اقتباسات المستخدم
    Route::get('/quotes', [QuoteController::class, 'index']); // كل الاقتباسات
    Route::post('/quotes', [QuoteController::class, 'store']); // إضافة اقتباس

    // جلب الكتب
    Route::get('/books', [BookController::class, 'index']);              // كل الكتب مع عدد الإعجابات
    Route::get('/books/{id}', [BookController::class, 'show']);         // تفاصيل كتاب مع likes_count
    Route::get('/books/{id}/with-likes', [BookController::class, 'getBookWithLikes']); // endpoint مخصص للـ likes
    Route::get('/me/books', [BookController::class, 'getUserBooks']);   // كتب المستخدم المملوكة

    // تحميل كتاب مؤقت بعد التحقق من الملكية
    Route::post('/books/{id}/download', [BookController::class, 'generateDownloadLink']);

    // الأسئلة الخاصة بالكتاب (المستخدم العادي)
    Route::get('/books/{bookId}/questions', [QuestionController::class, 'getBookQuestions']);

    // جلسات الإجابة (المستخدم العادي)
    Route::post('/books/{bookId}/session/start', [UserBookAnswerController::class,'startSession']);
    Route::post('/books/{bookId}/session/answer', [UserBookAnswerController::class,'recordAnswer']);
    Route::post('/books/{bookId}/session/submit', [UserBookAnswerController::class,'submitAnswers']);
    Route::post('/books/{bookId}/session/exit', [UserBookAnswerController::class,'exitSession']);

    // الجوائز والنقاط
    Route::get('/rewards', [RewardController::class,'index']);
    Route::post('/rewards/{id}/redeem', [RewardController::class,'redeem']);

    /* -------------------------------
       👑 مسارات الأدمن (Admin)
    -------------------------------- */
    Route::prefix('admin')->middleware('admin')->group(function () {

        // إدارة الأقسام
        Route::post('/categories', [CategoryController::class, 'store']); // إنشاء قسم جديد

        // إدارة الكتب
        Route::post('/categories/{categoryId}/books', [BookController::class, 'store']); // إضافة كتاب
        Route::put('/books/{id}', [BookController::class, 'update']);                     // تعديل كتاب
        Route::delete('/books/{id}', [BookController::class, 'destroy']);                // حذف كتاب

        // إدارة المستخدمين
        Route::get('/users', [AdminController::class, 'getAllUsers']);  // كل المستخدمين
        Route::post('/users', [AdminController::class, 'createUser']);  // ✅ إضافة مستخدم جديد بواسطة الأدمن
        Route::put('/users/{id}', [AdminController::class, 'updateUser']); // تعديل مستخدم
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']); // حذف مستخدم

        // إدارة الاقتباسات
        Route::delete('/quotes/{id}', [QuoteController::class, 'destroy']); // حذف اقتباس

        // إدارة الأسئلة (Admin)
        Route::post('/books/{bookId}/questions', [QuestionController::class,'store']); // إضافة سؤال
        Route::put('/questions/{id}', [QuestionController::class,'update']);          // تعديل سؤال
        Route::delete('/questions/{id}', [QuestionController::class,'destroy']);       // حذف سؤال

        // إدارة الإجابات (Admin)
        Route::post('/questions/{questionId}/answers', [AnswerController::class,'store']); // إضافة إجابة للسؤال
        Route::put('/answers/{id}', [AnswerController::class,'update']);                    // تعديل الإجابة
        Route::delete('/answers/{id}', [AnswerController::class,'destroy']);                // حذف الإجابة

        // إدارة المكافأت/النقاط
        Route::post('/rewards', [RewardController::class,'store']);   // إضافة مكافأة
        Route::post('/repoints', [RepointController::class,'store']); // إضافة نقاط
        Route::get('/repoints', [RepointController::class,'index']);  // عرض نقاط
    });
});

/* ------------------------------
   رابط عام لخدمة الملفات (signed URL)
--------------------------------- */
Route::get('/books/{id}/serve-download/{userId}', [BookController::class, 'serveDownload'])
     ->name('books.serveDownload');
