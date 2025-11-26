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

        return response()->json([
            'success' => true,
            'questions' => $questions
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

        // تحديد صحة الإجابة
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

        // إعادة الاستجابة مع نقاط الإجابة فقط و النقاط الكلية
        return response()->json([
            'message' => 'تم حفظ إجابتك',
            'record' => $record,
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,  // النقاط المكتسبة من هذه الإجابة فقط
            'total_points' => $user->points,   // النقاط الكلية للمستخدم
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
        $user = $request->user();

        $expectedQuestionIds = Question::where('book_id', $bookId)
            ->orderBy('id')
            ->take(3)
            ->pluck('id')
            ->toArray();

        if (count($expectedQuestionIds) === 0) {
            return response()->json(['message' => 'لا توجد أسئلة لهذا الكتاب'], 404);
        }

        $userAnswers = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('completed', false)
            ->get();

        $answeredIds = $userAnswers->pluck('question_id')->unique()->sort()->values()->toArray();

        $incomingAnswers = $request->input('answers', null);
        $newPoints = 0;

        DB::beginTransaction();
        try {
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

                    if (!in_array($qId, $expectedQuestionIds, true)) {
                        DB::rollBack();
                        return response()->json(['message' => 'إرسال سؤال غير صالح للجلسة: ' . $qId], 422);
                    }

                    $answer = Answer::where('id', $aId)->where('question_id', $qId)->first();
                    if (!$answer) {
                        DB::rollBack();
                        return response()->json(['message' => "الإجابة {$aId} لا تنتمي للسؤال {$qId}"], 422);
                    }

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

                $userAnswers = UserBookAnswer::where('user_id', $user->id)
                    ->where('book_id', $bookId)
                    ->where('completed', false)
                    ->get();

                $answeredIds = $userAnswers->pluck('question_id')->unique()->sort()->values()->toArray();
            }

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

            if ($newPoints > 0) {
                $user->increment('points', $newPoints);
                $user->refresh();
            }

            UserBookAnswer::where('user_id', $user->id)
                ->where('book_id', $bookId)
                ->where('completed', false)
                ->update(['completed' => true, 'updated_at' => now()]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'فشل إنهاء الجلسة', 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إنهاء الجلسة بنجاح.',
            'total_points' => $user->fresh()->points
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
                'message' => 'لا يمكنك الخروج الآن - لقد بدأت بالإجابة على أحد الأسئلة، أكمل الجلسة أو احذف الإجابات الجزئية.'
            ], 403);
        }

        return response()->json(['message' => 'تم الخروج. ستظهر الأسئلة عند الضغط على Accept لاحقًا.']);
    }
}
