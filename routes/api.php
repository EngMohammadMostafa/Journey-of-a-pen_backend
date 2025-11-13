<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\QuestionController;
use App\Http\Controllers\AnswerController;
use App\Http\Controllers\PurchasingController;
use App\Http\Controllers\UserBookAnswerController;
use App\Http\Controllers\PuyController;
use App\Http\Controllers\RewardController;
use App\Http\Controllers\RepointController;
use App\Http\Controllers\BookController;       // تأكد أن BookController موجود
use App\Http\Controllers\PaymentController;    // سنستخدمه لاحقًا لبدء جلسة Stripe (إنشائه لاحقًا)

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| هنا عرفنا كل مسارات الـ API المتعلقة بالأسئلة/الأجوبة، الشراء، الدفع، الجوائز.
| جميع المسارات داخل المجموعة محمية بـ auth:sanctum باستثناء Webhook للبايمنت.
|
*/

// -------------------------
// Routes التي تتطلب توكن
// -------------------------
Route::middleware(['auth:sanctum'])->group(function () {

    // -------------------------
    // إدارة الأسئلة (Admin only)
    // -------------------------
    // إنشاء سؤال لكتاب (للأدمن)
    Route::post('/admin/books/{bookId}/questions', [QuestionController::class,'store'])->middleware('admin');

    // تعديل سؤال (للأدمن)
    Route::put('/admin/questions/{id}', [QuestionController::class,'update'])->middleware('admin');

    // حذف سؤال (للأدمن)
    Route::delete('/admin/questions/{id}', [QuestionController::class,'destroy'])->middleware('admin');


    // -------------------------
    // إدارة خيارات الأسئلة (Admin only)
    // -------------------------
    // إضافة خيار لسؤال
    Route::post('/admin/questions/{questionId}/answers', [AnswerController::class,'store'])->middleware('admin');

    // تعديل خيار
    Route::put('/admin/answers/{id}', [AnswerController::class,'update'])->middleware('admin');

    // حذف خيار
    Route::delete('/admin/answers/{id}', [AnswerController::class,'destroy'])->middleware('admin');


    // -------------------------
    // عرض الأسئلة للمستخدم (بعد التأكد من امتلاك الكتاب)
    // -------------------------
    Route::get('/books/{bookId}/questions', [QuestionController::class,'getByBook']);


    // -------------------------
    // جلسات الأسئلة للمستخدم (Start / Submit / Exit)
    // - start: يتم استدعاؤه عند الضغط على "Accept" لبدء الجلسة
    // - submit: إرسال جميع الإجابات دفعة واحدة
    // - exit: الخروج (لا يحفظ إجابات جزئية)
    // -------------------------
    Route::post('/books/{bookId}/session/start', [UserBookAnswerController::class,'startSession']);
    Route::post('/books/{bookId}/session/submit', [UserBookAnswerController::class,'submitAnswers']);
    Route::post('/books/{bookId}/session/exit', [UserBookAnswerController::class,'exitSession']);


    // -------------------------
    // عمليات الشراء (Purchasing)
    // - index: قائمة المشتريات (للمستخدم أو للأدمن)
    // - show: تفاصيل عملية شراء
    // - create: إنشاء عملية شراء داخل القاعدة بعد تأكيد الدفع (يتم استدعاؤها بعد نجاح الدفع)
    // -------------------------
    Route::get('/purchases', [PurchasingController::class,'index']);
    Route::get('/purchases/{id}', [PurchasingController::class,'show']);
    Route::post('/purchases/create', [PurchasingController::class,'createPurchase']); // يستدعى بعد تأكيد الدفع


    // -------------------------
    // إدارة المدفوعات (Puy)
    // - عرض دفعات المستخدم أو كل الدفعات للأدمن
    // - Webhook للـ Stripe (موجود خارج مجموعة auth, انظر أسفل)
    // -------------------------
    Route::get('/payments', [PuyController::class,'index']);


    // -------------------------
    // الجوائز والنقاط
    // - الأدمن ينشئ الجوائز والسجلات (repoint)
    // - المستخدم يعرض الجوائز ويستبدل النقاط (redeem)
    // -------------------------
    Route::post('/admin/rewards', [RewardController::class,'store'])->middleware('admin');
    Route::get('/rewards', [RewardController::class,'index']);
    Route::post('/rewards/{id}/redeem', [RewardController::class,'redeem']);

    Route::post('/admin/repoints', [RepointController::class,'store'])->middleware('admin');
    Route::get('/repoints', [RepointController::class,'index'])->middleware('admin');


    // -------------------------
    // إضافات مفيدة (aliases) لتوافق ملف الـAPI المطلوب
    // -------------------------
    // عرض كل الكتب (pagination) - يستخدمه الـ frontend لعرض قائمة الكتب
    Route::get('/books', [BookController::class, 'index']);

    // عرض كتاب واحد مع تفاصيله
    Route::get('/books/{id}', [BookController::class, 'show']);

    // بدء عملية الشراء (سيقوم frontend باستدعاء هذا المسار لطلب جلسة دفع أو رابط Checkout)
    // ملاحظة: يمكن أن ننقله لاحقاً إلى مسار /payments/initiate أو إلى PaymentController
    Route::post('/books/{id}/purchase', [PaymentController::class, 'initiatePurchase']);
});


// -------------------------
// Webhook للمدفوعات (Stripe)
// ملاحظة مهمة: هذا المسار يجب أن يكون عام (بدون auth) لأن Stripe سيرسله من خوادمها.
// يجب أيضاً التحقق من التوقيع داخل الدالة (Stripe signature) لضمان الأمان.
// -------------------------
Route::post('/payments/webhook', [PuyController::class,'storeFromWebhook']);

