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
     * عرض أسئلة كتاب معين للمستخدم
     * يتحقق أن المستخدم يمتلك الكتاب ولم يكمل الأسئلة سابقًا
     */
    public function getBookQuestions($bookId)
    {
        $user = Auth::user();
        $book = Book::find($bookId);

        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        // تحقق أن المستخدم يمتلك الكتاب
        $owns = $user->books()->where('books.id', $bookId)->exists();
        if (!$owns) {
            return response()->json(['message' => 'لا يمكنك الوصول إلى الأسئلة قبل تحميل/شراء الكتاب'], 403);
        }

        // تحقق إذا أكمل الأسئلة سابقًا
        $alreadyAnswered = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('completed', true)
            ->exists();

        if ($alreadyAnswered) {
            return response()->json(['message' => 'لقد أكملت هذه الأسئلة سابقًا ولا يمكنك تكرارها'], 403);
        }

        // جلب الأسئلة مع الإجابات المرتبطة
        $questions = Question::with('answers')
            ->where('book_id', $book->id)
            ->take(3) // يمكن تعديل العدد حسب الحاجة
            ->get();

        return response()->json([
            'success' => true,
            'book_title' => $book->title,
            'questions' => $questions
        ]);
    }

    /**
     * Admin: إضافة سؤال جديد لكتاب
     */
    public function store(Request $request, $bookId)
    {
        $book = Book::find($bookId);
        if (!$book) return response()->json(['message' => 'الكتاب غير موجود'], 404);

        $data = $request->validate([
            'question_text' => 'required|string|max:1000',
        ]);

        $data['book_id'] = $bookId;

        $question = Question::create($data);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة السؤال للكتاب',
            'question' => $question
        ]);
    }

    /**
     * Admin: تعديل سؤال
     */
    public function update(Request $request, $id)
    {
        $question = Question::find($id);
        if (!$question) return response()->json(['message' => 'السؤال غير موجود'], 404);

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
     * Admin: حذف سؤال مع جميع الإجابات المرتبطة به
     */
    public function destroy($id)
    {
        $question = Question::find($id);
        if (!$question) return response()->json(['message' => 'السؤال غير موجود'], 404);

        // حذف جميع الإجابات المرتبطة قبل حذف السؤال
        $question->answers()->delete();
        $question->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف السؤال وكل الإجابات المتعلقة به'
        ]);
    }

    /**
     * استقبال إجابات المستخدم على الأسئلة
     * تبقى كما هي (تخزين إجابات المستخدم)
     */
    public function submitAnswers(Request $request, $bookId)
    {
        // المحتوى السابق لهذه الدالة
    }
}
