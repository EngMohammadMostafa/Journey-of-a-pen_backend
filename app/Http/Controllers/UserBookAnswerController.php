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
    
    public function startSession(Request $request, $bookId)
    {
        $user = $request->user();
        $book = Book::find($bookId);
        if (!$book) return response()->json(['message' => 'الكتاب غير موجود'], 404);

        
        $pivot = DB::table('book_user')
            ->where('book_id', $bookId)
            ->where('user_id', $user->id)
            ->first();

        if (!$pivot || (!$pivot->owned && !$pivot->downloaded_at)) {
            return response()->json(['message' => 'يجب تحميل/امتلاك الكتاب أولاً لتظهر الأسئلة'], 403);
        }

        
        $alreadyCompleted = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('completed', true)
            ->exists();

        if ($alreadyCompleted) {
            return response()->json(['message' => 'لقد أكملت أسئلة هذا الكتاب سابقًا'], 403);
        }

       
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
            'questions' => \App\Http\Resources\QuestionResource::collection($questions)
        ]);
    }

   
    public function recordAnswer(Request $request, $bookId)
    {
        $user = $request->user();

       
        $validator = Validator::make($request->all(), [
            'question_id' => 'required|integer|exists:questions,id',
            'answer_id' => 'required|integer|exists:answers,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $questionId = (int)$request->input('question_id');
        $answerId = (int)$request->input('answer_id');

       
        $expectedQuestions = Question::where('book_id', $bookId)->orderBy('id')->take(3)->pluck('id')->toArray();
        if (!in_array($questionId, $expectedQuestions, true)) {
            return response()->json(['message' => 'هذا السؤال غير صالح لهذه الجلسة'], 422);
        }

        
        $answer = Answer::where('id', $answerId)->where('question_id', $questionId)->first();
        if (!$answer) {
            return response()->json(['message' => 'الإجابة المختارة لا تنتمي لهذا السؤال'], 422);
        }

        
        $exists = UserBookAnswer::where([
            'user_id' => $user->id,
            'book_id' => $bookId,
            'question_id' => $questionId
        ])->exists();

        if ($exists) {
            return response()->json(['message' => 'لقد أجبت على هذا السؤال سابقًا'], 409);
        }

      
        $isCorrect = (bool)$answer->is_correct;

        
        $record = UserBookAnswer::create([
            'user_id' => $user->id,
            'book_id' => $bookId,
            'question_id' => $questionId,
            'answer_id' => $answerId,
            'is_correct' => $isCorrect,
            'completed' => false,
        ]);

       
        if ($isCorrect) {
            $user->increment('points', 1);
            $user->refresh();
        }

       
        $answeredCount = UserBookAnswer::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('completed', false)
            ->count();

      
        return response()->json([
            'message' => 'تم حفظ إجابتك',
            'record' => $record,
            'is_correct' => $isCorrect,
            'total_points' => $user->points,
            'answered_count' => $answeredCount,
            'can_exit' => $answeredCount === 0
        ], 201);
    }

   
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

            
            $sessionPoints = UserBookAnswer::where('user_id', $user->id)
                ->where('book_id', $bookId)
                ->where('completed', false)
                ->where('is_correct', true)
                ->count();

            
            if ($sessionPoints > 0) {
                $user->increment('points', $sessionPoints);
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
            'session_points' => $sessionPoints,    
            'total_points' => $user->points       
        ]);
    }

    
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
