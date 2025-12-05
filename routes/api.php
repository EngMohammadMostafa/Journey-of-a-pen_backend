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




Route::get('/', function () {
    return response()->json(['message' => 'API is running']);
});


Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']); 
    Route::post('/login', [AuthController::class, 'login']);       
});


Route::get('/categories', [CategoryController::class, 'index']);      
Route::get('/categories/{id}', [CategoryController::class, 'show']); 



Route::middleware(['auth:sanctum'])->group(function () {

    // تسجيل الخروج
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // بيانات المستخدم الحالي
    Route::get('/users/me', [UserController::class, 'getCurrentUser']);      
    Route::put('/users/me', [UserController::class, 'updateCurrentUser']);   
    Route::post('/users/me/change-password', [UserController::class, 'changePassword']); 

    
    Route::get('/users/points', [UserController::class, 'getTotalPoints']);
   
    
    Route::get('/quotes', [QuoteController::class, 'index']);  
    Route::post('/quotes', [QuoteController::class, 'store']); 
    

    Route::get('/books', [BookController::class, 'index']);                         
    Route::get('/books/{id}', [BookController::class, 'show']);                     
    Route::get('/books/{id}/with-likes', [BookController::class, 'getBookWithLikes']); 
    Route::get('/me/books', [BookController::class, 'getUserBooks']);              
  
    Route::post('/books/{id}/download', [BookController::class, 'generateDownloadLink']);

    
    Route::get('/books/{bookId}/questions', [QuestionController::class, 'getBookQuestions']);

   
    Route::post('/books/{bookId}/session/start', [UserBookAnswerController::class,'startSession']);
    Route::post('/books/{bookId}/session/answer', [UserBookAnswerController::class,'recordAnswer']);
    Route::post('/books/{bookId}/session/submit', [UserBookAnswerController::class,'submitAnswers']);
    Route::post('/books/{bookId}/session/exit', [UserBookAnswerController::class,'exitSession']);

    
    Route::get('/rewards', [RewardController::class,'index']);         
    Route::post('/rewards/{id}/redeem', [RewardController::class,'redeem']); 

    /* -------------------------------
       👑 مسارات الأدمن (Admin)
       - هذه المسارات محمية بميدلوير 'admin'
       - الأدمن الوحيد يمكنه إنشاء مستخدمين عاديين فقط
    -------------------------------- */
    Route::prefix('admin')->middleware('admin')->group(function () {

        // إدارة الأقسام
        Route::post('/categories', [CategoryController::class, 'store']);   // إنشاء قسم جديد
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']); // حذف قسم + كل الكتب المرتبطة به

        // إدارة الكتب
        Route::post('/categories/{categoryId}/books', [BookController::class, 'store']); // إضافة كتاب جديد
        Route::put('/books/{id}', [BookController::class, 'update']);                     // تعديل كتاب
        Route::delete('/books/{id}', [BookController::class, 'destroy']);                // حذف كتاب

        // إدارة المستخدمين
        Route::get('/users', [AdminController::class, 'getAllUsers']);       // عرض كل المستخدمين
        Route::get('/users/{id}', [AdminController::class, 'getUserById']);  // جلب مستخدم محدد
        Route::post('/users', [AdminController::class, 'createUser']);       // إنشاء مستخدم جديد
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);   // تعديل بيانات مستخدم
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']); // حذف مستخدم (لا يمكن حذف الأدمن الرئيسي)

        // إدارة الاقتباسات
        Route::delete('/quotes/{id}', [QuoteController::class, 'destroy']); // حذف اقتباس

        // إدارة الأسئلة (CRUD للأدمن)
        Route::post('/books/{bookId}/questions', [QuestionController::class,'store']); // إضافة سؤال
        Route::put('/questions/{id}', [QuestionController::class,'update']);          // تعديل سؤال
        Route::delete('/questions/{id}', [QuestionController::class,'destroy']);       // حذف سؤال

        // مسارات عرض الأسئلة للأدمن
        Route::get('/books/{bookId}/questions-with-answers', [QuestionController::class, 'adminGetBookQuestionsWithAnswers']);
        Route::get('/books/{bookId}/questions-with-correct-answers', [QuestionController::class, 'adminGetBookQuestionsWithCorrectAnswers']);
        Route::get('/books/{bookId}/questions/{questionId}', [QuestionController::class, 'adminShowQuestionForBook']);
        Route::get('/books/{bookId}/questions', [QuestionController::class, 'adminGetQuestionsOnly']);
        Route::get('/questions/{id}', [QuestionController::class, 'adminShowQuestion']);
        Route::get('/questions', [QuestionController::class, 'adminGetAllQuestions']); // عرض كل الأسئلة بدون الإجابات

        // إدارة الإجابات للأدمن
        Route::get('/answers', [AnswerController::class, 'adminGetAllAnswers']); // كل الإجابات
        Route::get('/questions/{questionId}/answers', [AnswerController::class, 'adminGetAnswersByQuestion']); 
        Route::get('/answers/{id}', [AnswerController::class, 'adminShowAnswer']); 
        Route::post('/questions/{questionId}/answers', [AnswerController::class,'store']); 
        Route::put('/answers/{id}', [AnswerController::class,'update']); 
        Route::delete('/answers/{id}', [AnswerController::class,'destroy']); 

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
