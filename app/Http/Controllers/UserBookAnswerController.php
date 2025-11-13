<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Question;
use App\Models\Answer;
use App\Models\UserBookAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserBookAnswerController extends Controller
{
    // المستخدم يطلب بدء جلسة أسئلة (يجب أن يكون قد حمل/اشتري الكتاب)
    public function startSession(Request $request, $bookId)
    {
        $user = $request->user();
        $book = Book::find($bookId);
        if (!$book) return response()->json(['message'=>'الكتاب غير موجود'], 404);

        // التحقق من أن المستخدم "يمتلك" الكتاب (اشترى أو تم ربطه في book_user)
        $owns = $user->books()->where('book_id', $bookId)->exists();
        if (!$owns) {
            return response()->json(['message'=>'يجب تحميل/شراء الكتاب أولاً قبل البدء بالأسئلة'], 403);
        }

        // التحقق إن كان المستخدم أنهى الأسئلة سابقًا
        $completed = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('completed', true)
            ->exists();

        if ($completed) {
            return response()->json(['message'=>'لقد أكملت هذه الأسئلة سابقًا ولا يمكنك تكرارها'], 403);
        }

        // نُرجع الأسئلة (3 أسئلة) مع الخيارات: الواجهة هي التي تمنع الخروج إذا بدأ الاجابة
        $questions = Question::with('answers')->where('book_id', $bookId)->take(3)->get();

        return response()->json(['questions'=>$questions]);
    }

    // المستخدم يرسل الاجابات (مصمم لاستقبال جميع الاجابات دفعة واحدة)
    public function submitAnswers(Request $request, $bookId)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'answers' => 'required|array|min:1', // array of { question_id, answer_id }
            'answers.*.question_id' => 'required|integer|exists:questions,id',
            'answers.*.answer_id' => 'required|integer|exists:answers,id'
        ]);

        if ($validator->fails()) return response()->json(['errors'=>$validator->errors()], 422);

        // منع الدخول إلى submit إن كان المستخدم قد أكمل مسبقاً
        $alreadyCompleted = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('completed', true)
            ->exists();

        if ($alreadyCompleted) {
            return response()->json(['message'=>'لقد أكملت الأسئلة مسبقًا'], 403);
        }

        $points = 0;
        $records = [];

        foreach ($request->answers as $ans) {
            $question = Question::find($ans['question_id']);
            $selected = Answer::find($ans['answer_id']);
            $isCorrect = $selected && $selected->is_correct ? true : false;

            $record = UserBookAnswer::create([
                'user_id' => $user->id,
                'book_id' => $bookId,
                'question_id' => $question->id,
                'answer_id' => $selected->id,
                'is_correct' => $isCorrect,
                'completed' => false,
            ]);
            $records[] = $record;
            if ($isCorrect) $points++;
        }

        // زيادة نقاط المستخدم
        $user->increment('points', $points);

        // تحديث جميع سجلات هذه الجلسة على أنها مكتملة
        UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->update(['completed' => true]);

        return response()->json([
            'message' => 'تم إرسال الإجابات',
            'points_earned' => $points,
            'total_points' => $user->points,
            'answers' => $records
        ]);
    }

    // المستخدم يضغط Exit قبل الإجابة على أي سؤال: لا نسجل أي شيء ونبقي completed=false
    public function exitSession(Request $request, $bookId)
    {
        $user = $request->user();
        // لا نسمح بالخروج إذا بدأ المستخدم بالإجابة على أي سؤال (هذا يتحقق على الواجهة)
        // لكن على مستوى الـAPI نتحقق إن كان هناك أي سجل غير مكتمل -> نرفض الخروج
        $partial = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('completed', false)
            ->exists();

        if ($partial) {
            return response()->json(['message'=>'لا يمكنك الخروج الآن - أكمل الإجابات أو أعد تشغيل الجلسة بعد حذف الجزئي'], 403);
        }

        return response()->json(['message'=>'تم الخروج - ستظهر الأسئلة عند فتح الكتاب لاحقًا']);
    }
}
