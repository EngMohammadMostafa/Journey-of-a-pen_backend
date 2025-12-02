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
     * - يتحقق من عدم حل الأسئلة مسبقًا
     * - يرجع 3 أسئلة فقط
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

        // التأكد أن المستخدم لم يكمل الأسئلة مسبقًا
        $alreadyAnswered = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('completed', true)
            ->exists();

        if ($alreadyAnswered) {
            return response()->json(['message' => 'لقد أكملت هذه الأسئلة سابقًا ولا يمكنك تكرارها'], 403);
        }

        // جلب الأسئلة + الإجابات (3 فقط)
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

        // حذف الإجابات المرتبطة
        $question->answers()->delete();

        // حذف السؤال
        $question->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف السؤال وكل الإجابات المتعلقة به'
        ]);
    }

    /**
     * ------------------------------
     * (ADMIN) جلب أسئلة كتاب مع كل الإجابات
     * ------------------------------
     * Route: GET /api/admin/books/{bookId}/questions-with-answers
     */
    public function adminGetBookQuestionsWithAnswers($bookId)
    {
        $book = Book::find($bookId);
        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        $questions = Question::with('answers')
            ->where('book_id', $bookId)
            ->get();

        return response()->json([
            'success' => true,
            'book_title' => $book->title,
            'questions' => $questions
        ]);
    }

    /**
     * ------------------------------
     * (ADMIN) جلب أسئلة كتاب مع الإجابات الصحيحة فقط
     * ------------------------------
     * Route: GET /api/admin/books/{bookId}/questions-with-correct-answers
     */
    public function adminGetBookQuestionsWithCorrectAnswers($bookId)
    {
        $book = Book::find($bookId);
        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        $questions = Question::with(['answers' => function($q) {
            $q->where('is_correct', true);
        }])->where('book_id', $bookId)->get();

        return response()->json([
            'success' => true,
            'book_title' => $book->title,
            'questions' => $questions
        ]);
    }

    /**
     * ------------------------------
     * (USER) إنهاء الجلسة + احتساب النقاط
     * ------------------------------
     * Route: POST /api/books/{bookId}/session/submit
     */
    public function submitAnswers(Request $request, $bookId)
    {
        // ... دالة موجودة مسبقًا (لم أعد كتابتها هنا لتقصير الكود)
    }

    /**
     * ======================================================================
     *                      دوال جديدة خاصة بعرض جدول الأسئلة
     * ======================================================================
     */

    /**
     * -----------------------------------------------------
     * (ADMIN) جلب كل الأسئلة لكتاب معيّن بدون الإجابات
     * (مناسب لعرض جدول الأسئلة كما في الصورة)
     * -----------------------------------------------------
     * Route: GET /api/admin/books/{bookId}/questions
     */
    public function adminGetQuestionsOnly($bookId)
    {
        $book = Book::find($bookId);
        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

        $questions = Question::where('book_id', $bookId)
            ->select('id', 'question_text', 'book_id', 'created_at', 'updated_at')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'book_title' => $book->title,
            'questions' => $questions
        ]);
    }

    /**
     * -----------------------------------------------------
     * (ADMIN) جلب سؤال واحد مع إجاباتـه
     * (مناسب لصفحة تعديل السؤال + تعديل الإجابات)
     * -----------------------------------------------------
     * Route: GET /api/admin/questions/{id}
     */
    public function adminShowQuestion($id)
    {
        $question = Question::with('answers')->find($id);
        if (!$question) {
            return response()->json(['message' => 'السؤال غير موجود'], 404);
        }

        return response()->json([
            'success' => true,
            'question' => $question
        ]);
    }

    /**
     * -----------------------------------------------------
     * (ADMIN) جلب كل الأسئلة من كل الكتب بدون الإجابات
     * (مناسب لعرض جدول الأسئلة في Admin Dashboard)
     * -----------------------------------------------------
     * Route: GET /api/admin/questions
     * يدعم Pagination: ?page=1&per_page=10
     * يمكن لاحقًا إضافة فلترة حسب الكتاب أو البحث النصي
     */
    public function adminGetAllQuestions(Request $request)
    {
        $query = Question::query();

        // فلترة حسب الكتاب إذا تم إرسال book_id
        if ($request->has('book_id')) {
            $query->where('book_id', $request->book_id);
        }

        // فلترة نصية على السؤال إذا تم إرسال search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('question_text', 'like', "%{$search}%");
        }

        // Pagination: عدد الأسئلة في الصفحة (افتراضي 10)
        $perPage = $request->get('per_page', 10);
        $questions = $query->select('id', 'question_text', 'book_id', 'created_at', 'updated_at')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $questions
        ]);
    }
}
