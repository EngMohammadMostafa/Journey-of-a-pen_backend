<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\QuestionController; // تحكم بالأسئلة
use App\Http\Controllers\AnswerController;   // تحكم بالإجابات
use App\Http\Controllers\UserBookAnswerController;
use App\Http\Controllers\RewardController;
use App\Http\Controllers\RepointController;
use App\Http\Controllers\CategoryController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| جميع المسارات المتعلقة بالمشروع:
| auth, users, books, questions, admin, rewards ...
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
Route::get('/categories', [CategoryController::class, 'index']);      // عرض كل الأقسام
Route::get('/categories/{id}', [CategoryController::class, 'show']); // عرض تفاصيل قسم معين

/* ------------------------------
   مسارات تتطلب مصادقة (auth:sanctum)
-------------------------------- */
Route::middleware(['auth:sanctum'])->group(function () {

    // تسجيل الخروج
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // بيانات المستخدم الحالي
    Route::get('/users/me', [UserController::class, 'getCurrentUser']);      // معلومات المستخدم الحالي
    Route::put('/users/me', [UserController::class, 'updateCurrentUser']);   // تعديل بيانات المستخدم الحالي
    Route::post('/users/me/change-password', [UserController::class, 'changePassword']); // تغيير كلمة المرور

    // اقتباسات المستخدم
    Route::get('/quotes', [QuoteController::class, 'index']);  // عرض كل الاقتباسات
    Route::post('/quotes', [QuoteController::class, 'store']); // إضافة اقتباس جديد

    // جلب الكتب
    Route::get('/books', [BookController::class, 'index']);                         // كل الكتب مع عدد الإعجابات
    Route::get('/books/{id}', [BookController::class, 'show']);                     // تفاصيل كتاب
    Route::get('/books/{id}/with-likes', [BookController::class, 'getBookWithLikes']); // تفاصيل كتاب + عدد likes
    Route::get('/me/books', [BookController::class, 'getUserBooks']);              // كتب المستخدم المملوكة

    // تحميل كتاب مؤقت بعد التحقق من الملكية
    Route::post('/books/{id}/download', [BookController::class, 'generateDownloadLink']);

    // الأسئلة الخاصة بالكتاب (المستخدم العادي)
    Route::get('/books/{bookId}/questions', [QuestionController::class, 'getBookQuestions']);

    // جلسات الإجابة على الأسئلة (للمستخدم العادي)
    Route::post('/books/{bookId}/session/start', [UserBookAnswerController::class,'startSession']);
    Route::post('/books/{bookId}/session/answer', [UserBookAnswerController::class,'recordAnswer']);
    Route::post('/books/{bookId}/session/submit', [UserBookAnswerController::class,'submitAnswers']);
    Route::post('/books/{bookId}/session/exit', [UserBookAnswerController::class,'exitSession']);

    // الجوائز والنقاط
    Route::get('/rewards', [RewardController::class,'index']);           // عرض كل المكافآت
    Route::post('/rewards/{id}/redeem', [RewardController::class,'redeem']); // استبدال مكافأة

    /* -------------------------------
       👑 مسارات الأدمن (Admin)
       - هذه المسارات محمية بميدلوير 'admin'
       - الأدمن الوحيد يمكنه إنشاء مستخدمين عاديين فقط
    -------------------------------- */
    Route::prefix('admin')->middleware('admin')->group(function () {

        // إدارة الأقسام
        Route::post('/categories', [CategoryController::class, 'store']); // إنشاء قسم جديد

        // إدارة الكتب
        Route::post('/categories/{categoryId}/books', [BookController::class, 'store']); // إضافة كتاب جديد
        Route::put('/books/{id}', [BookController::class, 'update']);                     // تعديل كتاب
        Route::delete('/books/{id}', [BookController::class, 'destroy']);                // حذف كتاب

        // إدارة المستخدمين
        Route::get('/users', [AdminController::class, 'getAllUsers']);       // عرض كل المستخدمين

        // 🔹 إضافة: جلب مستخدم محدد عبر الـ ID (مفيد لصفحات العرض/التعديل)
        Route::get('/users/{id}', [AdminController::class, 'getUserById']); // جلب مستخدم واحد

        Route::post('/users', [AdminController::class, 'createUser']);       // إنشاء مستخدم جديد (دائمًا عادي)
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);   // تعديل بيانات مستخدم
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']); // حذف مستخدم (لا يمكن حذف الأدمن الرئيسي)

        // إدارة الاقتباسات
        Route::delete('/quotes/{id}', [QuoteController::class, 'destroy']); // حذف اقتباس

        // إدارة الأسئلة (CRUD للأدمن)
        Route::post('/books/{bookId}/questions', [QuestionController::class,'store']); // إضافة سؤال
        Route::put('/questions/{id}', [QuestionController::class,'update']);          // تعديل سؤال
        Route::delete('/questions/{id}', [QuestionController::class,'destroy']);       // حذف سؤال

        /*
         * -------------------------
         * مسارات عرض الأسئلة للأدمن
         * -------------------------
         */

        // 1) يعيد كل أسئلة كتاب محدد مع جميع الإجابات (is_correct ظاهر)
        Route::get('/books/{bookId}/questions-with-answers', [QuestionController::class, 'adminGetBookQuestionsWithAnswers']);

        // 2) يعيد كل أسئلة كتاب محدد مع الإجابات الصحيحة فقط (is_correct ظاهر)
        Route::get('/books/{bookId}/questions-with-correct-answers', [QuestionController::class, 'adminGetBookQuestionsWithCorrectAnswers']);

        // 3) يعيد سؤال محدد داخل كتاب محدد مع الإجابات (is_correct ظاهر)
        Route::get('/books/{bookId}/questions/{questionId}', [QuestionController::class, 'adminShowQuestionForBook']);

        /*
         * 4) يعيد كل الأسئلة لكتاب محدد **بدون** الإجابات (مناسب لعرض جدول الأسئلة فقط)
         */
        Route::get('/books/{bookId}/questions', [QuestionController::class, 'adminGetQuestionsOnly']);

        /*
         * 5) يعيد سؤال واحد حسب الـ id (مناسب لصفحة التعديل — يجلب بيانات السؤال فقط)
         */
        Route::get('/questions/{id}', [QuestionController::class, 'adminShowQuestion']);

        /*
         * -------------------------
         * 🔹 إضافة جديدة: يعيد كل الأسئلة من كل الكتب **بدون الإجابات** (Admin Dashboard)
         * مثال استهلاك من الفرونت: GET /api/admin/questions
         * يدعم Pagination: ?page=1&per_page=10
         * يمكن لاحقاً إضافة فلترة: ?book_id=3 أو ?search=نص
         */
        Route::get('/questions', [QuestionController::class, 'adminGetAllQuestions']);

        /*
         * -------------------------
         * مسارات عرض/إدارة الإجابات للأدمن
         * ------------------------- 
         */
        Route::get('/answers', [AnswerController::class, 'adminGetAllAnswers']);                // كل الإجابات (مع Pagination)
        Route::get('/questions/{questionId}/answers', [AnswerController::class, 'adminGetAnswersByQuestion']); // كل الإجابات لسؤال محدد
        Route::get('/answers/{id}', [AnswerController::class, 'adminShowAnswer']);             // إجابة واحدة

        // إدارة الإجابات (CRUD)
        Route::post('/questions/{questionId}/answers', [AnswerController::class,'store']); // إضافة إجابة
        Route::put('/answers/{id}', [AnswerController::class,'update']);                    // تعديل إجابة
        Route::delete('/answers/{id}', [AnswerController::class,'destroy']);                // حذف إجابة

        // إدارة المكافآت/النقاط
        Route::post('/rewards', [RewardController::class,'store']);   // إضافة مكافأة
        Route::post('/repoints', [RepointController::class,'store']); // إضافة نقاط
        Route::get('/repoints', [RepointController::class,'index']);  // عرض النقاط
    });
});

/* ------------------------------
   رابط عام لخدمة الملفات (signed URL)
--------------------------------- */
Route::get('/books/{id}/serve-download/{userId}', [BookController::class, 'serveDownload'])
     ->name('books.serveDownload'); // رابط تحميل الكتاب مؤقتًا للمستخدم
