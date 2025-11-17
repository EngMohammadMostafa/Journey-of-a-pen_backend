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
|
| هنا المسارات المتعلقة بالمشروع: auth, users, books, questions, admin, rewards ...
|
*/

/* ------------------------------
   مسارات مفتوحة (بدون auth)
-------------------------------- */
Route::get('/', function () {
    return response()->json(['message' => 'API is running']);
});

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

/* الأقسام متاحة للعامة */
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);

/* --------------------------------------------------------------------------------
   المسارات التي تحتاج مصادقة (auth:sanctum) — عدّل الـ middleware إذا تستخدم passport
   -------------------------------------------------------------------------------- */
Route::middleware(['auth:sanctum'])->group(function () {

    // مصادقة المستخدم
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // بيانات المستخدم
    Route::get('/users/me', [UserController::class, 'getCurrentUser']);
    Route::put('/users/me', [UserController::class, 'updateCurrentUser']);
    Route::post('/users/me/change-password', [UserController::class, 'changePassword']);

    // اقتباسات
    Route::get('/quotes', [QuoteController::class, 'index']);
    Route::post('/quotes', [QuoteController::class, 'store']);

    /* -------------------------------
       👑 مسارات الأدمن (تتطلب middleware admin)
       تأكد أن middleware 'admin' معرفة في Kernel.php وتتحقق من صلاحية المستخدم.
    -------------------------------- */
    Route::prefix('admin')->middleware('admin')->group(function () {

        // إدارة الأقسام
        Route::post('/categories', [CategoryController::class, 'store']);

        // إنشاء كتاب في قسم معين (Admin)
        Route::post('/categories/{categoryId}/books', [BookController::class, 'store']);

        // إدارة المستخدمين
        Route::get('/users', [AdminController::class, 'getAllUsers']);
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);

        // إدارة الاقتباسات
        Route::delete('/quotes/{id}', [QuoteController::class, 'destroy']);

        // إدارة الأسئلة (Admin)
        Route::post('/books/{bookId}/questions', [QuestionController::class,'store']);
        Route::put('/questions/{id}', [QuestionController::class,'update']);
        Route::delete('/questions/{id}', [QuestionController::class,'destroy']);

        // إدارة الإجابات (Admin)
        Route::post('/questions/{questionId}/answers', [AnswerController::class,'store']);
        Route::put('/answers/{id}', [AnswerController::class,'update']);
        Route::delete('/answers/{id}', [AnswerController::class,'destroy']);

        // إدارة المكافأت/النقاط
        Route::post('/rewards', [RewardController::class,'store']);
        Route::post('/repoints', [RepointController::class,'store']);
        Route::get('/repoints', [RepointController::class,'index']);
    });

    /* -------------------------------
       📚 الكتب (مستخدم مسجّل)
    -------------------------------- */
    Route::get('/books', [BookController::class, 'index']);        // جلب كل الكتب
    Route::get('/books/{id}', [BookController::class, 'show']);   // تفاصيل كتاب
    Route::get('/me/books', [BookController::class, 'getUserBooks']); // كتب المملوكة للمستخدم

    // توليد رابط تحميل مؤقت بعد التحقق من الملكية
    Route::post('/books/{id}/download', [BookController::class, 'generateDownloadLink']);

    /* -------------------------------
       ❓ الأسئلة الخاصة بالكتاب
    -------------------------------- */
    Route::get('/books/{bookId}/questions', [QuestionController::class, 'getBookQuestions']);

    /* -------------------------------
       📝 جلسات الإجابة (start / answer / submit / exit)
       مسارات مرتبطة بإدارة جلسات إجابة المستخدم على أسئلة الكتاب
    -------------------------------- */
    Route::post('/books/{bookId}/session/start', [UserBookAnswerController::class,'startSession']);
    Route::post('/books/{bookId}/session/answer', [UserBookAnswerController::class,'recordAnswer']);
    Route::post('/books/{bookId}/session/submit', [UserBookAnswerController::class,'submitAnswers']);
    Route::post('/books/{bookId}/session/exit', [UserBookAnswerController::class,'exitSession']);

    /* -------------------------------
       🎁 الجوائز والنقاط
    -------------------------------- */
    Route::get('/rewards', [RewardController::class,'index']);
    Route::post('/rewards/{id}/redeem', [RewardController::class,'redeem']);
});

/* ------------------------------
   رابط عام لخدمة الملف (signed URL)
   هذا الـ route عام لأن الرابط الموقّع قد يُستدعى بدون توكن.
--------------------------------- */
Route::get('/books/{id}/serve-download/{userId}', [BookController::class, 'serveDownload'])
     ->name('books.serveDownload');
