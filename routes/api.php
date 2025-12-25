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
use App\Http\Controllers\CompetitionController;
use App\Http\Controllers\CompetitionBookController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RequestBookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return response()->json(['message' => 'API is running']);
});

// ================== AUTH ==================
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']); 
    Route::post('/login', [AuthController::class, 'login']);       
});

// ================== PUBLIC ==================
Route::get('/categories', [CategoryController::class, 'index']);      
Route::get('/categories/{id}', [CategoryController::class, 'show']); 

// ================== PROTECTED (AUTH USER) ==================
Route::middleware(['auth:sanctum'])->group(function () {

    // ---------- USER PROFILE ----------
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/users/me', [UserController::class, 'getCurrentUser']);      
    Route::put('/users/me', [UserController::class, 'updateCurrentUser']);   
    Route::post('/users/me/change-password', [UserController::class, 'changePassword']); 
    Route::get('/users/points', [UserController::class, 'getTotalPoints']);

    // ---------- QUOTES ----------
    Route::get('/quotes', [QuoteController::class, 'index']);  
    Route::post('/quotes', [QuoteController::class, 'store']); 

    // ---------- BOOKS ----------
    Route::get('/books', [BookController::class, 'index']);                         
    Route::get('/books/{id}', [BookController::class, 'show']);                     
    Route::get('/books/{id}/with-likes', [BookController::class, 'getBookWithLikes']); 
    Route::get('/me/books', [BookController::class, 'getUserBooks']);              

    // ---------- BOOK SEARCH (جديد) ----------
    // البحث عن الكتب حسب العنوان أو اسم المؤلف
    Route::get('/books/search', [BookController::class, 'searchBooks']); 

    // إنشاء رابط تحميل (محمي بالتوكن)
    Route::post('/books/{id}/download', [BookController::class, 'generateDownloadLink']);

    // ❗ التحميل الفعلي (Signed URL فقط)
    Route::get('/books/{id}/serve-download/{userId}', [BookController::class, 'serveDownload'])
        ->withoutMiddleware(['auth:sanctum'])
        ->name('books.serveDownload');

    // ---------- PURCHASE ----------
    Route::post('/books/{id}/purchase', [BookController::class, 'purchaseBook']); 
    Route::get('/me/purchased-books', [BookController::class, 'purchasedBooks']); 

    // ---------- BOOK QUESTIONS ----------
    Route::get('/books/{bookId}/questions', [QuestionController::class, 'getBookQuestions']);
    Route::post('/books/{bookId}/session/start', [UserBookAnswerController::class,'startSession']);
    Route::post('/books/{bookId}/session/answer', [UserBookAnswerController::class,'recordAnswer']);
    Route::post('/books/{bookId}/session/submit', [UserBookAnswerController::class,'submitAnswers']);
    Route::post('/books/{bookId}/session/exit', [UserBookAnswerController::class,'exitSession']);

    // ---------- COMPETITIONS (USER) ----------
    Route::get('/competitions', [CompetitionController::class, 'index']);
    Route::get('/competitions/{id}/books', [CompetitionBookController::class, 'index']); 
    Route::post('/competitions/{id}/participate', [CompetitionBookController::class, 'store']); 
    Route::post('/competition-books/{id}/like', [CompetitionBookController::class, 'like']); 
    Route::get('/competition-books/{id}/download', [CompetitionBookController::class, 'download']); 

    // ---------- NOTIFICATIONS ----------
    Route::get('/notifications', [NotificationController::class, 'index']);

    // ---------- REQUEST BOOKS ----------
    Route::post('/request-books', [RequestBookController::class, 'store']);
    Route::get('/request-books/my-requests', function (Request $request) {
        return \App\Models\RequestBook::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();
    });

    // ---------- LIKE / UNLIKE ----------
    Route::post('/books/{id}/toggle-like', [BookController::class, 'toggleLike']);
});

// ================== ADMIN ==================
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {

    // ---------- CATEGORIES ----------
    Route::post('/categories', [CategoryController::class, 'store']);   
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']); 

    // ---------- BOOKS ----------
    Route::post('/categories/{categoryId}/books', [BookController::class, 'store']); 
    Route::put('/books/{id}', [BookController::class, 'update']);                     
    Route::delete('/books/{id}', [BookController::class, 'destroy']);                

    // ---------- USERS ----------
    Route::get('/users', [AdminController::class, 'getAllUsers']);       
    Route::get('/users/{id}', [AdminController::class, 'getUserById']);  
    Route::post('/users', [AdminController::class, 'createUser']);       
    Route::put('/users/{id}', [AdminController::class, 'updateUser']);   
    Route::delete('/users/{id}', [AdminController::class, 'deleteUser']); 

    // ---------- QUOTES ----------
    Route::delete('/quotes/{id}', [QuoteController::class, 'destroy']); 

    // ---------- QUESTIONS & ANSWERS ----------
    Route::post('/books/{bookId}/questions', [QuestionController::class,'store']); 
    Route::put('/questions/{id}', [QuestionController::class,'update']);          
    Route::delete('/questions/{id}', [QuestionController::class,'destroy']);       

    // ---------- COMPETITIONS ----------
    Route::get('/competitions', [CompetitionController::class, 'adminIndex']); 
    Route::post('/competitions', [CompetitionController::class, 'store']); 
    Route::put('/competitions/{id}', [CompetitionController::class, 'update']); 
    Route::delete('/competitions/{id}', [CompetitionController::class, 'destroy']); 

    // ---------- REQUEST BOOKS ----------
    Route::get('/request-books', [RequestBookController::class, 'index']);
    Route::post('/request-books/{id}/accept', [RequestBookController::class, 'accept']);
    Route::post('/request-books/{id}/reject', [RequestBookController::class, 'reject']);
    Route::get('/request-books/{id}/download', [RequestBookController::class, 'downloadFile']);


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

     // ---------- NOTIFICATIONS (ADMIN) ----------
     Route::post('/notifications', [NotificationController::class, 'store']); // إضافة إشعار
     Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']); // حذف إشعار

      // ---------- COMPETITION BOOKS (ADMIN) ----------
    // تفاصيل كتب المسابقة للأدمن
    Route::get('/competitions/{competition_id}/details', [CompetitionBookController::class, 'adminCompetitionDetails']);

    // عرض لايكات كتاب معين
    Route::get('/competition-books/{competition_book_id}/likes', [CompetitionBookController::class, 'adminBookLikes']);

    // قبول / رفض كتاب
    Route::post('/competition-books/{competition_book_id}/approve-or-reject', [CompetitionBookController::class, 'approveOrReject']);

    // إضافة كتاب من المسابقة للمنصة
    Route::post('/competition-books/{competition_book_id}/add-to-platform', [CompetitionBookController::class, 'addToPlatform']);

    // حذف كتاب من المسابقة
    Route::delete('/competition-books/{competition_book_id}', [CompetitionBookController::class, 'destroy']);


    // ---------- STATS ----------
    Route::get('/stats/total-books', [AdminController::class, 'totalBooks']); 
    Route::get('/stats/total-questions', [AdminController::class, 'totalQuestions']); 
    Route::get('/stats/total-competitions', [AdminController::class, 'totalCompetitions']); 
});
