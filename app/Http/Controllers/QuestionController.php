<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Question;
use App\Models\Answer;
use App\Models\UserBookAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuestionController extends Controller
{
    /**
     * 🟢 عرض الأسئلة الخاصة بكتاب معين
     * (تظهر فقط إذا كان المستخدم قد حمّل الكتاب)
     */
    public function getBookQuestions($bookId)
    {
        $user = Auth::user();
        $book = Book::find($bookId);

        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        // التأكد أنه حمّل الكتاب (أو اشتراه)
        $downloaded = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->exists();

        if (!$downloaded) {
            return response()->json(['message' => 'لا يمكنك الوصول إلى الأسئلة قبل تحميل الكتاب'], 403);
        }

        // ✅ إذا لم يجب على الأسئلة بعد
        $alreadyAnswered = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('completed', true)
            ->exists();

        if ($alreadyAnswered) {
            return response()->json(['message' => 'لقد أجبت مسبقًا على أسئلة هذا الكتاب'], 403);
        }

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
     * 🟢 استقبال إجابات المستخدم على الأسئلة الثلاث
     */
    public function submitAnswers(Request $request, $bookId)
    {
        $user = Auth::user();
        $answers = $request->input('answers'); // مصفوفة من: [question_id, answer_id]

        $book = Book::find($bookId);
        if (!$book) return response()->json(['message' => 'الكتاب غير موجود'], 404);

        $pointsEarned = 0;

        foreach ($answers as $answer) {
            $question = Question::find($answer['question_id']);
            $selectedAnswer = Answer::find($answer['answer_id']);

            $isCorrect = $selectedAnswer && $selectedAnswer->is_correct;

            // حفظ إجابة المستخدم
            UserBookAnswer::create([
                'user_id' => $user->id,
                'book_id' => $book->id,
                'question_id' => $question->id,
                'answer_id' => $selectedAnswer->id,
                'is_correct' => $isCorrect,
                'completed' => false,
            ]);

            if ($isCorrect) $pointsEarned++;
        }

        // تحديث النقاط
        $user->increment('points', $pointsEarned);

        // تعليم أنه أنهى الأسئلة كلها
        UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->update(['completed' => true]);

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال الإجابات بنجاح',
            'points_earned' => $pointsEarned,
            'total_points' => $user->points
        ]);
    }
}
