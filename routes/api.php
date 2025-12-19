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

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
|
*/

Route::get('/', function () {
    return response()->json(['message' => 'API is running']);
});

// ----------------- Auth -----------------
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']); 
    Route::post('/login', [AuthController::class, 'login']);       
});

// ----------------- Public Categories -----------------
Route::get('/categories', [CategoryController::class, 'index']);      
Route::get('/categories/{id}', [CategoryController::class, 'show']); 

// ----------------- Protected Routes -----------------
Route::middleware(['auth:sanctum'])->group(function () {

    // ----------------- User Profile -----------------
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/users/me', [UserController::class, 'getCurrentUser']);      
    Route::put('/users/me', [UserController::class, 'updateCurrentUser']);   
    Route::post('/users/me/change-password', [UserController::class, 'changePassword']); 
    Route::get('/users/points', [UserController::class, 'getTotalPoints']);

    // ----------------- Quotes -----------------
    Route::get('/quotes', [QuoteController::class, 'index']);  
    Route::post('/quotes', [QuoteController::class, 'store']); 

    // ----------------- Books -----------------
    Route::get('/books', [BookController::class, 'index']);                         
    Route::get('/books/{id}', [BookController::class, 'show']);                     
    Route::get('/books/{id}/with-likes', [BookController::class, 'getBookWithLikes']); 
    Route::get('/me/books', [BookController::class, 'getUserBooks']);              
    Route::post('/books/{id}/download', [BookController::class, 'generateDownloadLink']);
    Route::get('/books/{id}/serve-download/{userId}', [BookController::class, 'serveDownload'])
         ->name('books.serveDownload');

    // ----------------- Book Questions -----------------
    Route::get('/books/{bookId}/questions', [QuestionController::class, 'getBookQuestions']);
    Route::post('/books/{bookId}/session/start', [UserBookAnswerController::class,'startSession']);
    Route::post('/books/{bookId}/session/answer', [UserBookAnswerController::class,'recordAnswer']);
    Route::post('/books/{bookId}/session/submit', [UserBookAnswerController::class,'submitAnswers']);
    Route::post('/books/{bookId}/session/exit', [UserBookAnswerController::class,'exitSession']);

    // ----------------- Rewards -----------------
    Route::get('/rewards', [RewardController::class,'index']);         
    Route::post('/rewards/{id}/redeem', [RewardController::class,'redeem']); 

    // ----------------- Competitions for Users -----------------
    Route::get('/competitions', [CompetitionController::class, 'index']); // جميع المسابقات المتاحة
    Route::get('/competitions/{id}/books', [CompetitionBookController::class, 'index']); // عرض كتب المسابقة (بدون أسماء)
    Route::post('/competitions/{id}/participate', [CompetitionBookController::class, 'store']); // رفع كتاب للمسابقة
    Route::post('/competition-books/{id}/like', [CompetitionBookController::class, 'like']); // لايك / إلغاء لايك
    Route::get('/competition-books/{id}/download', [CompetitionBookController::class, 'download']); // تحميل الكتاب

    // ----------------- Admin Routes -----------------
    Route::prefix('admin')->middleware('admin')->group(function () {

        // Categories
        Route::post('/categories', [CategoryController::class, 'store']);   
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']); 

        // Books
        Route::post('/categories/{categoryId}/books', [BookController::class, 'store']); 
        Route::put('/books/{id}', [BookController::class, 'update']);                     
        Route::delete('/books/{id}', [BookController::class, 'destroy']);                

        // Users
        Route::get('/users', [AdminController::class, 'getAllUsers']);       
        Route::get('/users/{id}', [AdminController::class, 'getUserById']);  
        Route::post('/users', [AdminController::class, 'createUser']);       
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);   
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']); 

        // Quotes
        Route::delete('/quotes/{id}', [QuoteController::class, 'destroy']); 

        // Questions & Answers
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

        // Rewards & Repoints
        Route::post('/rewards', [RewardController::class,'store']);   
        Route::post('/repoints', [RepointController::class,'store']); 
        Route::get('/repoints', [RepointController::class,'index']);  

        // ----------------- Competitions for Admin -----------------
        Route::get('/competitions', [CompetitionController::class, 'adminIndex']); // عرض كل المسابقات
        Route::post('/competitions', [CompetitionController::class, 'store']); // إنشاء مسابقة جديدة
        Route::put('/competitions/{id}', [CompetitionController::class, 'update']); // تعديل مسابقة
        Route::delete('/competitions/{id}', [CompetitionController::class, 'destroy']); // حذف مسابقة
        Route::get('/competitions/{id}/books', [CompetitionBookController::class, 'adminLikes']); // عرض كتب المسابقة مع أسماء المشاركين
        Route::delete('/competition-books/{id}', [CompetitionBookController::class, 'destroy']); // حذف كتاب مشارك
        Route::post('/competition-books/{id}/add-to-platform', [CompetitionBookController::class, 'addToPlatform']); // إضافة كتاب فائز للمنصة
    });
});
