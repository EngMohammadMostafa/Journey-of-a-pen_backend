<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Question;
use App\Models\Answer;
use App\Models\UserBookAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserBookAnswerController extends Controller
{
    /**
     * Start session (Accept) — يعيد 3 أسئلة دفعة واحدة.
     * الإجابات المرسلة للمستخدم لا تحتوي على is_correct لأننا نستخدم Resources.
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

        // تحقق إن أنهى المستخدم الأسئلة سابقًا
        $alreadyCompleted = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('completed', true)
            ->exists();

        if ($alreadyCompleted) {
            return response()->json(['message' => 'لقد أكملت أسئلة هذا الكتاب سابقًا'], 403);
        }

        // جلب الأسئلة الثلاث (ترتيب حسب id)
        $questions = Question::with('answers')
            ->where('book_id', $bookId)
            ->orderBy('id')
            ->take(3)
            ->get();

        if ($questions->count() === 0) {
            return response()->json(['message' => 'لا توجد أسئلة لهذا الكتاب'], 404);
        }

        // نُعيد الأسئلة — الافتراض أن لديك QuestionResource الذي يخفي is_correct للمستخدم العادي
        return response()->json([
            'success' => true,
            'questions' => \App\Http\Resources\QuestionResource::collection($questions)
        ]);
    }

    /**
     * Record a single answer (user answers one question).
     * - يمنع الإجابة المكررة على نفس السؤال
     * - يخزن النتيجة في جدول UserBookAnswer ويعيد هل كانت صحيحة للمستخدم فقط
     */
    public function recordAnswer(Request $request, $bookId)
    {
        $user = $request->user();

        // التحقق من صحة البيانات القادمة
        $validator = Validator::make($request->all(), [
            'question_id' => 'required|integer|exists:questions,id',
            'answer_id' => 'required|integer|exists:answers,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $questionId = (int)$request->input('question_id');
        $answerId = (int)$request->input('answer_id');

        // التأكد أن السؤال من نفس الكتاب (ضمن الثلاثة المختارة في الجلسة)
        $expectedQuestions = Question::where('book_id', $bookId)->orderBy('id')->take(3)->pluck('id')->toArray();
        if (!in_array($questionId, $expectedQuestions, true)) {
            return response()->json(['message' => 'هذا السؤال غير صالح لهذه الجلسة'], 422);
        }

        // التأكد أن الإجابة تنتمي فعلاً للسؤال
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

        // تحديد صحة الإجابة
        $isCorrect = (bool)$answer->is_correct;

        // حفظ سجل إجابة المستخدم
        $record = UserBookAnswer::create([
            'user_id' => $user->id,
            'book_id' => $bookId,
            'question_id' => $questionId,
            'answer_id' => $answerId,
            'is_correct' => $isCorrect,
            'completed' => false,
        ]);

        // منح النقطة فورياً إن كانت صحيحة
        if ($isCorrect) {
            $user->increment('points', 1);
            $user->refresh();
        }

        // عدد الإجابات الحالية في الجلسة (غير مكتملة)
        $answeredCount = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('completed', false)
            ->count();

        // إعادة نتيجة إجابة المستخدم
        return response()->json([
            'message' => 'تم حفظ إجابتك',
            'record' => $record,
            'is_correct' => $isCorrect,
            'total_points' => $user->points,
            'answered_count' => $answeredCount,
            'can_exit' => $answeredCount === 0
        ], 201);
    }

    /**
     * Submit all answers (Finish).
     * - يتحقق أن المستخدم أجاب على الأسئلة المطلوبة (3)
     * - يدعم إرسال الإجابات دفعة واحدة أو الاعتماد على السجلات الموجودة
     * - يعين completed = true، ويحسب النقاط للجلسة ويحدث النقاط الكلية
     */
    public function submitAnswers(Request $request, $bookId)
    {
        $user = $request->user();

        // الأسئلة المتوقعة للجلسة (ثلاثة)
        $expectedQuestionIds = Question::where('book_id', $bookId)
            ->orderBy('id')
            ->take(3)
            ->pluck('id')
            ->toArray();

        if (count($expectedQuestionIds) === 0) {
            return response()->json(['message' => 'لا توجد أسئلة لهذا الكتاب'], 404);
        }

        // إجابات المستخدم الحالية (غير مكتملة)
        $userAnswers = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('completed', false)
            ->get();

        $answeredIds = $userAnswers->pluck('question_id')->unique()->sort()->values()->toArray();

        $incomingAnswers = $request->input('answers', null); // يمكن إرسال مصفوفة إجابات لتعويض المفقود
        $newPoints = 0;

        DB::beginTransaction();
        try {
            // معالجة الإجابات الجديدة إذا أرسلها المستخدم
            if (is_array($incomingAnswers) && count($incomingAnswers) > 0) {
                $validator = Validator::make($request->all(), [
                    'answers' => 'required|array',
                    'answers.*.question_id' => 'required|integer|exists:questions,id',
                    'answers.*.answer_id' => 'required|integer|exists:answers,id',
                ]);
                if ($validator->fails()) {
                    DB::rollBack();
                    return response()->json(['errors' => $validator->errors()], 422);
                }

                foreach ($incomingAnswers as $ans) {
                    $qId = (int)$ans['question_id'];
                    $aId = (int)$ans['answer_id'];

                    // تحقق أن السؤال ضمن جلسة الثلاثة
                    if (!in_array($qId, $expectedQuestionIds, true)) {
                        DB::rollBack();
                        return response()->json(['message' => 'إرسال سؤال غير صالح للجلسة: ' . $qId], 422);
                    }

                    // تحقق أن الإجابة تنتمي للسؤال
                    $answer = Answer::where('id', $aId)->where('question_id', $qId)->first();
                    if (!$answer) {
                        DB::rollBack();
                        return response()->json(['message' => "الإجابة {$aId} لا تنتمي للسؤال {$qId}"], 422);
                    }

                    // إضافة السجلات المفقودة
                    $exists = UserBookAnswer::where([
                        'user_id' => $user->id,
                        'book_id' => $bookId,
                        'question_id' => $qId
                    ])->exists();

                    if (!$exists) {
                        $isCorrect = (bool)$answer->is_correct;
                        UserBookAnswer::create([
                            'user_id' => $user->id,
                            'book_id' => $bookId,
                            'question_id' => $qId,
                            'answer_id' => $aId,
                            'is_correct' => $isCorrect,
                            'completed' => false,
                        ]);
                        if ($isCorrect) $newPoints++;
                    }
                }

                // تحديث قائمة الإجابات بعد الإضافة
                $userAnswers = UserBookAnswer::where('user_id', $user->id)
                    ->where('book_id', $bookId)
                    ->where('completed', false)
                    ->get();

                $answeredIds = $userAnswers->pluck('question_id')->unique()->sort()->values()->toArray();
            }

            // تأكد أن المستخدم أجاب على كل الأسئلة
            sort($expectedQuestionIds);
            sort($answeredIds);
            if ($answeredIds !== $expectedQuestionIds) {
                DB::rollBack();
                return response()->json([
                    'message' => 'يجب الإجابة على جميع الأسئلة قبل الضغط على Finish',
                    'expected_questions' => $expectedQuestionIds,
                    'answered_questions' => $answeredIds
                ], 422);
            }

            // حساب نقاط الجلسة الحالية
            $sessionPoints = UserBookAnswer::where('user_id', $user->id)
                ->where('book_id', $bookId)
                ->where('completed', false)
                ->where('is_correct', true)
                ->count();

            // إضافة النقاط للجلسة إلى المجموع الكلي
            if ($sessionPoints > 0) {
                $user->increment('points', $sessionPoints);
                $user->refresh();
            }

            // تعيين completed = true لكل سجلات الجلسة
            UserBookAnswer::where('user_id', $user->id)
                ->where('book_id', $bookId)
                ->where('completed', false)
                ->update(['completed' => true, 'updated_at' => now()]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'فشل إنهاء الجلسة', 'error' => $e->getMessage()], 500);
        }

        // إعادة النقاط بعد إنهاء الجلسة
        return response()->json([
            'success' => true,
            'message' => 'تم إنهاء الجلسة بنجاح.',
            'session_points' => $sessionPoints,    // نقاط هذه الجلسة فقط
            'total_points' => $user->points        // مجموع النقاط الكلي
        ]);
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
