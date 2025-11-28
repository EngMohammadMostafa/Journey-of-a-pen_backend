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
use App\Http\Resources\QuestionResource;

class QuestionController extends Controller
{
    /**
     * ------------------------------
     * (USER) جلب أسئلة كتاب معيّن
     * ------------------------------
     * - يتحقق أن المستخدم يملك الكتاب
     * - يتحقق أن المستخدم لم يكمل الأسئلة سابقًا (completed = true)
     * - يرجع 3 أسئلة فقط
     * - AnswerResource يخفي is_correct للمستخدم العادي
     *
     * Route: GET /api/books/{bookId}/questions
     */
    public function getBookQuestions($bookId)
    {
        $user = Auth::user();
        $book = Book::find($bookId);

        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        // التأكد أن المستخدم يمتلك الكتاب
        $owns = $user->books()->where('books.id', $bookId)->exists();
        if (!$owns) {
            return response()->json(['message' => 'لا يمكنك الوصول إلى الأسئلة قبل تحميل/شراء الكتاب'], 403);
        }

        // التحقق من أن المستخدم لم يكمل الأسئلة سابقًا
        $alreadyAnswered = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('completed', true)
            ->exists();

        if ($alreadyAnswered) {
            return response()->json(['message' => 'لقد أكملت هذه الأسئلة سابقًا ولا يمكنك تكرارها'], 403);
        }

        // جلب الأسئلة مع الإجابات (3 فقط)
        $questions = Question::with('answers')
            ->where('book_id', $bookId)
            ->take(3)
            ->get();

        return response()->json([
            'success' => true,
            'book_title' => $book->title,
            'questions' => QuestionResource::collection($questions)
        ]);
    }

    /**
     * ------------------------------
     * (ADMIN) إضافة سؤال لكتاب
     * ------------------------------
     * Route: POST /api/admin/books/{bookId}/questions
     */
    public function store(Request $request, $bookId)
    {
        $book = Book::find($bookId);
        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        $data = $request->validate([
            'question_text' => 'required|string|max:1000',
        ]);

        $data['book_id'] = $bookId;

        $question = Question::create($data);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة السؤال للكتاب',
            'question' => $question
        ], 201);
    }

    /**
     * ------------------------------
     * (ADMIN) تعديل سؤال
     * ------------------------------
     * Route: PUT /api/admin/questions/{id}
     */
    public function update(Request $request, $id)
    {
        $question = Question::find($id);
        if (!$question) {
            return response()->json(['message' => 'السؤال غير موجود'], 404);
        }

        $data = $request->validate([
            'question_text' => 'required|string|max:1000',
        ]);

        $question->update($data);

        return response()->json([
            'success' => true,
            'message' => 'تم تعديل السؤال',
            'question' => $question
        ]);
    }

    /**
     * ------------------------------
     * (ADMIN) حذف سؤال + إجاباته
     * ------------------------------
     * Route: DELETE /api/admin/questions/{id}
     */
    public function destroy($id)
    {
        $question = Question::find($id);
        if (!$question) {
            return response()->json(['message' => 'السؤال غير موجود'], 404);
        }

        // حذف الإجابات المرتبطة بالسؤال
        $question->answers()->delete();

        // حذف السؤال نفسه
        $question->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف السؤال وكل الإجابات المتعلقة به'
        ]);
    }

    /**
     * ------------------------------
     * (USER) إنهاء الجلسة + احتساب النقاط
     * ------------------------------
     * - يجب أن يجيب المستخدم على الأسئلة الثلاثة
     * - يتم التحقق من صحة الإجابات
     * - يتم تسجيل النقاط
     * - يتم تحديث completed = true لكل الإجابات
     *
     * Route: POST /api/books/{bookId}/session/submit
     */
    public function submitAnswers(Request $request, $bookId)
    {
        $user = Auth::user();

        // جلب الأسئلة المطلوبة (3 أسئلة فقط)
        $expectedQuestionIds = Question::where('book_id', $bookId)
            ->orderBy('id')
            ->take(3)
            ->pluck('id')
            ->toArray();

        if (count($expectedQuestionIds) === 0) {
            return response()->json(['message' => 'لا توجد أسئلة لهذا الكتاب'], 404);
        }

        // جلب الإجابات الحالية (غير مكتملة)
        $userAnswers = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('completed', false)
            ->get();

        $answeredIds = $userAnswers->pluck('question_id')->unique()->sort()->values()->toArray();

        $incomingAnswers = $request->input('answers', null); 
        $newPoints = 0;

        DB::beginTransaction();
        try {
            // لو المستخدم أرسل الإجابات دفعة واحدة
            if (is_array($incomingAnswers) && count($incomingAnswers) > 0) {

                // تحقق التحقق من الطلب
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

                    // التأكد أن السؤال فعلاً ضمن الجلسة
                    if (!in_array($qId, $expectedQuestionIds, true)) {
                        DB::rollBack();
                        return response()->json(['message' => 'إرسال سؤال غير صالح للجلسة: ' . $qId], 422);
                    }

                    // التأكد أن الإجابة تنتمي للسؤال
                    $answer = Answer::where('id', $aId)->where('question_id', $qId)->first();
                    if (!$answer) {
                        DB::rollBack();
                        return response()->json(['message' => "الإجابة {$aId} لا تنتمي للسؤال {$qId}"], 422);
                    }

                    // إذا لم يسبق تسجيل إجابة لهذا السؤال
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

                        if ($isCorrect) {
                            $newPoints++;
                        }
                    }
                }

                // تحديث الإجابات بعد الإدخال الجديد
                $userAnswers = UserBookAnswer::where('user_id', $user->id)
                    ->where('book_id', $bookId)
                    ->where('completed', false)
                    ->get();

                $answeredIds = $userAnswers->pluck('question_id')->unique()->sort()->values()->toArray();
            }

            // يجب أن يجيب المستخدم على جميع الأسئلة
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

            // إضافة نقاط للمستخدم
            if ($newPoints > 0) {
                $user->increment('points', $newPoints);
            }

            // تعيين completed = true
            UserBookAnswer::where('user_id', $user->id)
                ->where('book_id', $bookId)
                ->where('completed', false)
                ->update(['completed' => true, 'updated_at' => now()]);

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'فشل إنهاء الجلسة',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إنهاء الجلسة بنجاح.',
            'total_points' => $user->fresh()->points
        ]);
    }
}
