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

/*
|--------------------------------------------------------------------------
| API Routes (بدون Stripe)
|--------------------------------------------------------------------------
|
| هذا الملف يحتوي على:
| 🔐 التسجيل/الدخول
| 👤 بيانات المستخدم
| 📚 الكتب (بما فيها توليد رابط التحميل المؤقت)
| ❓ الأسئلة والأجوبة
| 📝 جلسات الإجابة (start / answer / submit / exit)
| 💬 الاقتباسات
| 👑 مهام الأدمن
| 🎁 الجوائز والنقاط
|
*/

/* ------------------------------
   مسارات مفتوحة (بدون auth)
-------------------------------- */
Route::get('/', function () {
    return response()->json(['message' => 'API is running']);
});

// تسجيل + تسجيل دخول
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

/* -------------------------------------
   مسارات تحتاج auth:sanctum (توكين)
-------------------------------------- */
Route::middleware(['auth:sanctum'])->group(function () {

    /* -------------------------------
       🔐 مصادقة المستخدم
    -------------------------------- */
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/users/me', [UserController::class, 'getCurrentUser']);
    Route::put('/users/me', [UserController::class, 'updateCurrentUser']);
    Route::post('/users/me/change-password', [UserController::class, 'changePassword']);

    /* -------------------------------
       💬 الاقتباسات (مستخدم مسجّل)
    -------------------------------- */
    Route::get('/quotes', [QuoteController::class, 'index']);
    Route::post('/quotes', [QuoteController::class, 'store']);

    /* -------------------------------
       👑 مسارات الأدمن
    -------------------------------- */
    Route::prefix('admin')->middleware('admin')->group(function () {

        // إدارة المستخدمين
        Route::get('/users', [AdminController::class, 'getAllUsers']);
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);

        // حذف اقتباس
        Route::delete('/quotes/{id}', [QuoteController::class, 'destroy']);

        // إدارة الأسئلة (Admin)
        Route::post('/books/{bookId}/questions', [QuestionController::class,'store']);
        Route::put('/questions/{id}', [QuestionController::class,'update']);
        Route::delete('/questions/{id}', [QuestionController::class,'destroy']);

        // إدارة الإجابات (Admin)
        Route::post('/questions/{questionId}/answers', [AnswerController::class,'store']);
        Route::put('/answers/{id}', [AnswerController::class,'update']);
        Route::delete('/answers/{id}', [AnswerController::class,'destroy']);

        // إدارة مثيرات النقاط / الجوائز (Admin)
        Route::post('/rewards', [RewardController::class,'store']);
        Route::post('/repoints', [RepointController::class,'store']);
        Route::get('/repoints', [RepointController::class,'index']);
    });

    /* -------------------------------
       📚 الكتب (مستخدم مسجّل)
    -------------------------------- */
    Route::get('/books', [BookController::class, 'index']);   // قائمة الكتب (pagination)
    Route::get('/books/{id}', [BookController::class, 'show']); // تفاصيل كتاب

    // توليد رابط تحميل مؤقت (يُطلب بعد التحقق من امتلاك/تحميل الكتاب)
    Route::post('/books/{id}/download', [BookController::class, 'generateDownloadLink']);

    /* -------------------------------
       ❓ عرض الأسئلة الخاصة بكتاب
    -------------------------------- */
    Route::get('/books/{bookId}/questions', [QuestionController::class, 'getBookQuestions']);

    /* -------------------------------
       📝 جلسات الإجابة (Start / Answer / Submit / Exit)
       - start: يعيد الثلاثة أسئلة دفعة واحدة عند الضغط على Accept
       - answer: حفظ إجابة واحدة فورًا (recordAnswer)
       - submit: إنهاء الجلسة (Finish) بعد الإجابة على كل الأسئلة
       - exit: الخروج إذا لم يبدأ المستخدم بالإجابة على أي سؤال
    -------------------------------- */
    Route::post('/books/{bookId}/session/start', [UserBookAnswerController::class,'startSession']);
    Route::post('/books/{bookId}/session/answer', [UserBookAnswerController::class,'recordAnswer']);
    Route::post('/books/{bookId}/session/submit', [UserBookAnswerController::class,'submitAnswers']);
    Route::post('/books/{bookId}/session/exit', [UserBookAnswerController::class,'exitSession']);

    /* -------------------------------
       🎁 الجوائز
    -------------------------------- */
    Route::get('/rewards', [RewardController::class,'index']);
    Route::post('/rewards/{id}/redeem', [RewardController::class,'redeem']);
});

/* ------------------------------
   مسارات عامة (بدون مصادقة)
--------------------------------- */

// رابط عام موقع (signed) لخدمة الملف — يجب أن يكون عامًا لأن الرابط قد يزور من دون توكن
Route::get('/books/{id}/serve-download/{userId}', [BookController::class, 'serveDownload'])
     ->name('books.serveDownload');
