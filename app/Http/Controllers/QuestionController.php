<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Question;
use App\Models\Answer;
use App\Models\UserBookAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class QuestionController extends Controller
{
    /**
     * عرض الأسئلة الخاصة بكتاب معين (تظهر فقط إذا كان المستخدم "يمتلك" الكتاب)
     */
    public function getBookQuestions($bookId)
    {
        $user = Auth::user();
        $book = Book::find($bookId);

        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        // تحقق أن المستخدم يمتلك الكتاب (pivot book_user) أو أنه اشتراه
        $owns = $user->books()->where('books.id', $bookId)->exists();
        if (!$owns) {
            return response()->json(['message' => 'لا يمكنك الوصول إلى الأسئلة قبل تحميل/شراء الكتاب'], 403);
        }

        // اذا أكمل الأسئلة سابقًا لا تسمح له
        $alreadyAnswered = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('completed', true)
            ->exists();

        if ($alreadyAnswered) {
            return response()->json(['message' => 'لقد أكملت هذه الأسئلة سابقًا ولا يمكنك تكرارها'], 403);
        }

        // جلب الأسئلة (حتى 3 أسئلة أو عدد الأسئلة المتاحة إن أقل)
        $questions = Question::with('answers')
            ->where('book_id', $book->id)
            ->take(3)
            ->get();

        return response()->json([
            'success' => true,
            'book_title' => $book->title,
            'questions' => $questions
        ]);
    }

    /**
     * استقبال إجابات المستخدم على الأسئلة (استقبال دفعة من الإجابات)
     * المتوقَّع body:
     * {
     *   "answers": [
     *     {"question_id": 1, "answer_id": 7},
     *     {"question_id": 2, "answer_id": 9},
     *     {"question_id": 3, "answer_id": 12}
     *   ]
     * }
     */
    public function submitAnswers(Request $request, $bookId)
    {
        $user = Auth::user();

        $book = Book::find($bookId);
        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        // تحقق أن المستخدم يمتلك الكتاب
        $owns = $user->books()->where('books.id', $bookId)->exists();
        if (!$owns) {
            return response()->json(['message' => 'يجب تحميل/شراء الكتاب أولاً قبل الإجابة'], 403);
        }

        // منع الإرسال إن كان المستخدم قد أكمل مسبقاً
        $alreadyCompleted = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('completed', true)
            ->exists();

        if ($alreadyCompleted) {
            return response()->json(['message' => 'لقد أجبت مسبقًا على أسئلة هذا الكتاب'], 403);
        }

        // تحقق من بنية الطلب
        $validator = Validator::make($request->all(), [
            'answers' => 'required|array|min:1',
            'answers.*.question_id' => 'required|integer|exists:questions,id',
            'answers.*.answer_id' => 'required|integer|exists:answers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $answersInput = $request->input('answers');

        // جلب الأسئلة المتوقعة من DB (التي تخص الكتاب) وعددها
        $questions = Question::where('book_id', $bookId)->take(3)->get();
        $expectedQuestionIds = $questions->pluck('id')->toArray();

        // تحقق أن المستخدم أرسل إجابات لكل سؤال المتوقع (ولا يوجد سؤال خارجي)
        $submittedQuestionIds = array_column($answersInput, 'question_id');

        sort($expectedQuestionIds);
        $uniqueSubmitted = array_values(array_unique($submittedQuestionIds));
        sort($uniqueSubmitted);

        if ($uniqueSubmitted !== $expectedQuestionIds) {
            return response()->json([
                'message' => 'يجب إرسال الإجابات لجميع الأسئلة المطلوبة وبنفس المعرفات.'
            ], 422);
        }

        $pointsEarned = 0;
        $createdRecords = [];

        // استخدم معاملة لحماية الإدخالات
        DB::beginTransaction();
        try {
            foreach ($answersInput as $ans) {
                $question = Question::find($ans['question_id']);
                $selectedAnswer = Answer::find($ans['answer_id']);

                // تحقق أن الإجابة تنتمي للسؤال نفسه
                if ($selectedAnswer->question_id !== $question->id) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "الاختيار answer_id={$selectedAnswer->id} لا يتوافق مع question_id={$question->id}"
                    ], 422);
                }

                $isCorrect = $selectedAnswer->is_correct ? true : false;

                $record = UserBookAnswer::create([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'question_id' => $question->id,
                    'answer_id' => $selectedAnswer->id,
                    'is_correct' => $isCorrect,
                    'completed' => false,
                ]);

                $createdRecords[] = $record;
                if ($isCorrect) $pointsEarned++;
            }

            // بعد إدخال كل السجلات نعلّمها مكتملة
            UserBookAnswer::where('user_id', $user->id)
                ->where('book_id', $book->id)
                ->update(['completed' => true]);

            // زيادة نقاط المستخدم
            $user->increment('points', $pointsEarned);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ أثناء حفظ الإجابات', 'error' => $e->getMessage()], 500);
        }

        // جلب قيمة النقاط المحدثة
        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال الإجابات وحساب النقاط بنجاح',
            'points_earned' => $pointsEarned,
            'total_points' => $user->points,
            'answers' => $createdRecords
        ], 201);
    }
}
