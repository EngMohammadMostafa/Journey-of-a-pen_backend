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
    
    public function getBookQuestions($bookId)
    {
        $user = Auth::user();
        $book = Book::find($bookId);

        if (!$book) {
            return response()->json(['message' => 'الكتاب غير موجود'], 404);
        }

       
        $owns = $user->books()->where('books.id', $bookId)->exists();
        if (!$owns) {
            return response()->json(['message' => 'لا يمكنك الوصول إلى الأسئلة قبل تحميل/شراء الكتاب'], 403);
        }

       
        $alreadyAnswered = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('completed', true)
            ->exists();

        if ($alreadyAnswered) {
            return response()->json(['message' => 'لقد أكملت هذه الأسئلة سابقًا ولا يمكنك تكرارها'], 403);
        }

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

  
    public function destroy($id)
    {
        $question = Question::find($id);
        if (!$question) {
            return response()->json(['message' => 'السؤال غير موجود'], 404);
        }

        
        $question->answers()->delete();

        $question->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف السؤال وكل الإجابات المتعلقة به'
        ]);
    }

    
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

   
    public function submitAnswers(Request $request, $bookId)
    {
        // ... دالة موجودة مسبقًا
    }

    
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

    
    public function adminGetAllQuestions(Request $request)
    {
        $query = Question::query();

        if ($request->has('book_id')) {
            $query->where('book_id', $request->book_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('question_text', 'like', "%{$search}%");
        }

        $perPage = $request->get('per_page', 10);
        $questions = $query->select('id', 'question_text', 'book_id', 'created_at', 'updated_at')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $questions
        ]);
    }
    

    
    public function adminShowQuestionForBook($bookId, $questionId)
    {
       
        $question = Question::where('book_id', $bookId)
             ->with('answers')
             ->find($questionId);

        if (!$question) {
        return response()->json(['message' => 'السؤال غير موجود لهذا الكتاب'], 404);
        }
         return response()->json([
        'success' => true,
        'question' => $question
       ]);
    }


   
    public function adminGetTotalQuestions()
    {
        $total = Question::count();

        return response()->json([
            'success' => true,
            'total_questions' => $total
        ]);
    }
}
