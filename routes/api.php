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
use App\Http\Controllers\RequestBookController; // ✅ Controller طلبات الكتب

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| جميع مسارات الـ API الخاصة بالمنصة
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

    Route::post('/books/{id}/download', [BookController::class, 'generateDownloadLink']);
    Route::get('/books/{id}/serve-download/{userId}', [BookController::class, 'serveDownload'])
         ->name('books.serveDownload');

    // ✅ شراء كتاب مدفوع وإضافته لسلة المشتريات
    Route::post('/books/{id}/purchase', [BookController::class, 'purchaseBook']); 
    // ✅ عرض سلة المشتريات (Purchased Books)
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

    // ---------- NOTIFICATIONS (USER) ----------
    Route::get('/notifications', [NotificationController::class, 'index']);

    // ================== 📚 REQUEST BOOKS (USER) ==================
    Route::post('/request-books', [RequestBookController::class, 'store']);
    Route::get('/request-books/my-requests', function (Request $request) {
        return \App\Models\RequestBook::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();
    });

    // ================== 📌 API Like / Unlike للكتب ==================
    // عند استدعاء هذا الـ API:
    // إذا كان المستخدم قد أعجب مسبقًا بالكتاب سيتم إلغاء الإعجاب،
    // وإذا لم يكن أعجب به سيتم تسجيل إعجاب جديد.
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
    Route::get('/books/{bookId}/questions-with-answers', [QuestionController::class, 'adminGetBookQuestionsWithAnswers']);
    Route::get('/books/{bookId}/questions-with-correct-answers', [QuestionController::class, 'adminGetBookQuestionsWithCorrectAnswers']);
    Route::get('/books/{bookId}/questions/{questionId}', [QuestionController::class, 'adminShowQuestionForBook']);
    Route::get('/books/{bookId}/questions', [QuestionController::class, 'adminGetQuestionsOnly']);
    Route::get('/questions/{id}', [QuestionController::class, 'adminShowQuestion']);
    Route::get('/questions', [QuestionController::class, 'adminGetAllQuestions']); 
    Route::get('/answers', [AnswerController::class, 'adminGetAllAnswers']); 
    Route::get('/questions/{questionId}/answers', [AnswerController::class, 'adminGetAnswersByQuestion']); 
    Route::get('/answers/{id}', [AnswerController::class, 'adminShowAnswer']); 
    Route::post('/questions/{questionId}/answers', [AnswerController::class,'store']); 
    Route::put('/answers/{id}', [AnswerController::class,'update']); 
    Route::delete('/answers/{id}', [AnswerController::class,'destroy']); 

    

    // ---------- COMPETITIONS (ADMIN) ----------
    Route::get('/competitions', [CompetitionController::class, 'adminIndex']); 
    Route::post('/competitions', [CompetitionController::class, 'store']); 
    Route::put('/competitions/{id}', [CompetitionController::class, 'update']); 
    Route::delete('/competitions/{id}', [CompetitionController::class, 'destroy']); 
    Route::get('/competitions/{id}/details', [CompetitionBookController::class, 'adminCompetitionDetails']); 
    Route::get('/competition-books/{id}/likes', [CompetitionBookController::class, 'adminBookLikes']); 
    Route::get('/competitions/{id}/books', [CompetitionBookController::class, 'adminLikes']); 
    Route::delete('/competition-books/{id}', [CompetitionBookController::class, 'destroy']); 
    Route::post('/competition-books/{id}/add-to-platform', [CompetitionBookController::class, 'addToPlatform']); 
    Route::post('/competition-books/{id}/approve-or-reject', [CompetitionBookController::class, 'approveOrReject']); 

    // ---------- NOTIFICATIONS (ADMIN) ----------
    Route::post('/notifications', [NotificationController::class, 'store']); 
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']); 

    // ================== 📚 REQUEST BOOKS (ADMIN) ==================
    Route::get('/request-books', [RequestBookController::class, 'index']);
    Route::post('/request-books/{id}/accept', [RequestBookController::class, 'accept']);
    Route::post('/request-books/{id}/reject', [RequestBookController::class, 'reject']);

    // ✅ تحميل ملف طلب الكتاب للأدمن
    Route::get('/request-books/{id}/download', [RequestBookController::class, 'downloadFile']);

    // ================== 📊 ADMIN STATS ==================
    Route::get('/stats/total-books', [AdminController::class, 'totalBooks']); 
    Route::get('/stats/total-questions', [AdminController::class, 'totalQuestions']); 
    Route::get('/stats/total-competitions', [AdminController::class, 'totalCompetitions']); 
});
