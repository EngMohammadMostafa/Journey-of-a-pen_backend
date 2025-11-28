<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Question;
use App\Models\Answer;
use App\Models\UserBookAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\QuestionResource;

class UserBookAnswerController extends Controller
{
    /**
     * Start session (Accept) — يعيد 3 أسئلة دفعة واحدة.
     * الآن يستخدم QuestionResource حتى لا يظهر is_correct للمستخدم العادي.
     */
    public function startSession(Request $request, $bookId)
    {
        $user = $request->user();
        $book = Book::find($bookId);
        if (!$book) return response()->json(['message' => 'الكتاب غير موجود'], 404);

        // تحقق من امتلاك/تحميل الكتاب من pivot (book_user)
        $pivot = DB::table('book_user')
            ->where('book_id', $bookId)
            ->where('user_id', $user->id)
            ->first();

        if (!$pivot || (!$pivot->owned && !$pivot->downloaded_at)) {
            return response()->json(['message' => 'يجب تحميل/امتلاك الكتاب أولاً لتظهر الأسئلة'], 403);
        }

        // تحقق إن كان المستخدم أنهى الأسئلة سابقًا
        $alreadyCompleted = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('completed', true)
            ->exists();

        if ($alreadyCompleted) {
            return response()->json(['message' => 'لقد أكملت أسئلة هذا الكتاب سابقًا'], 403);
        }

        // جلب الأسئلة الثلاث (ثابتة: ترتيب حسب id)
        $questions = Question::with('answers')
            ->where('book_id', $bookId)
            ->orderBy('id')
            ->take(3)
            ->get();

        if ($questions->count() === 0) {
            return response()->json(['message' => 'لا توجد أسئلة لهذا الكتاب'], 404);
        }

        // نُرجع الموارد — الإجابات لن تحتوي على is_correct للمستخدم العادي
        return response()->json([
            'success' => true,
            'questions' => QuestionResource::collection($questions)
        ]);
    }

    /**
     * Record a single answer (called when user answers one question).
     * - يمنع الإجابة على نفس السؤال مرتين
     * - يحسب النقطة فوراً إن كانت الإجابة صحيحة
     * - لا يقوم بعمل completed=true هنا (يتم عند submit)
     */
    public function recordAnswer(Request $request, $bookId)
    {
        $user = $request->user();

        // التحقق من صحة البيانات الواردة
        $validator = Validator::make($request->all(), [
            'question_id' => 'required|integer|exists:questions,id',
            'answer_id' => 'required|integer|exists:answers,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $questionId = (int)$request->input('question_id');
        $answerId = (int)$request->input('answer_id');

        // التأكد أن السؤال من الكتاب وأنه من ضمن 3 أسئلة للجلسة
        $expectedQuestions = Question::where('book_id', $bookId)->orderBy('id')->take(3)->pluck('id')->toArray();
        if (!in_array($questionId, $expectedQuestions, true)) {
            return response()->json(['message' => 'هذا السؤال غير صالح لهذه الجلسة'], 422);
        }

        // التأكد أن الإجابة تنتمي للسؤال
        $answer = Answer::where('id', $answerId)->where('question_id', $questionId)->first();
        if (!$answer) {
            return response()->json(['message' => 'الإجابة المختارة لا تنتمي لهذا السؤال'], 422);
        }

        // منع الإجابة على نفس السؤال أكثر من مرة
        $exists = UserBookAnswer::where([
            'user_id' => $user->id,
            'book_id' => $bookId,
            'question_id' => $questionId
        ])->exists();

        if ($exists) {
            return response()->json(['message' => 'لقد أجبت على هذا السؤال سابقًا'], 409);
        }

        // تحديد صحة الإجابة (يُستخدم داخلياً فقط)
        $isCorrect = (bool)$answer->is_correct;

        // حفظ الإجابة في جدول UserBookAnswer
        $record = UserBookAnswer::create([
            'user_id' => $user->id,
            'book_id' => $bookId,
            'question_id' => $questionId,
            'answer_id' => $answerId,
            'is_correct' => $isCorrect,
            'completed' => false,
        ]);

        // حساب النقاط للإجابة الحالية فقط
        $pointsEarned = $isCorrect ? 1 : 0;
        if ($pointsEarned > 0) {
            $user->increment('points', $pointsEarned); // زيادة نقاط المستخدم فقط إذا الإجابة صحيحة
            $user->refresh();
        }

        // عدد الإجابات الحالية في هذه الجلسة (غير مكتملة)
        $answeredCount = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('completed', false)
            ->count();

        // إعادة الاستجابة للمستخدم: لا نُظهر is_correct الإجمالي لكل الخيارات
        // بل نُخبره فقط إن كانت إجابته صحيحة أم لا، ونُعيد السجل المخزن
        return response()->json([
            'message' => 'تم حفظ إجابتك',
            'record' => $record,
            'is_correct' => $isCorrect,            // هذه تظهر للمستخدم ليرى نتيجة إجابته فقط
            'points_earned' => $pointsEarned,
            'total_points' => $user->points,
            'answered_count' => $answeredCount,
            'can_exit' => $answeredCount === 0
        ], 201);
    }

    /**
     * Submit all answers (Finish).
     * - يتحقق أن المستخدم أجاب على كل الأسئلة (3) — وإلا سيرفض
     * - عند النجاح: يعين completed = true لكل سجلات هذه الجلسة
     */
    public function submitAnswers(Request $request, $bookId)
    {
        // انسخ هنا نفس دالتك الحالية كما أرسلتها في الكود السابق.
        // لقد قمت بإرسالها مسبقاً في محادثتنا — إن أردت سأُدرجها كاملة هنا أيضاً.
        return response()->json(['message' => 'submitAnswers موجودة مسبقاً — أخبرني إن تريد أن أدرجها هنا بنفس المحتوى.']);
    }

    /**
     * Exit session:
     * - يسمح فقط إذا لم يجب المستخدم على أي سؤال لهذه الجلسة بعد
     * - إذا كانت هناك إجابات جزئية يرفض الخروج
     */
    public function exitSession(Request $request, $bookId)
    {
        $user = $request->user();

        $partialExists = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('completed', false)
            ->exists();

        if ($partialExists) {
            return response()->json([
                'message' => 'لا يمكنك الخروج الآن لأنك بدأت بالإجابة على أحد الأسئلة، أكمل الجلسة الحالية أولاً'
            ], 403);
        }

        return response()->json(['message' => 'تم الخروج. ستظهر الأسئلة عند الضغط على Accept لاحقًا.']);
    }
}
