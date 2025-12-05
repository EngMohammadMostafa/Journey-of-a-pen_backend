<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\AnswerResource; 
class AnswerController extends Controller
{
    
    public function store(Request $request, $questionId)
    {
        $validator = Validator::make($request->all(), [
            'answer_text' => 'required|string|max:255',
            'is_correct' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $question = Question::find($questionId);
        if (!$question) {
            return response()->json(['message' => 'السؤال غير موجود'], 404);
        }

        $answer = Answer::create([
            'answer_text' => $request->answer_text,
            'is_correct' => $request->boolean('is_correct', false),
            'question_id' => $question->id
        ]);

        return response()->json([
            'message' => 'تم إنشاء خيار',
            'answer' => new AnswerResource($answer)
        ], 201);
    }

    
    public function update(Request $request, $id)
    {
        $answer = Answer::find($id);
        if (!$answer) {
            return response()->json(['message' => 'الخيار غير موجود'], 404);
        }

        $validator = Validator::make($request->all(), [
            'answer_text' => 'sometimes|string|max:255',
            'is_correct' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $answer->update($validator->validated());

        return response()->json([
            'message' => 'تم تعديل الخيار',
            'answer' => new AnswerResource($answer)
        ]);
    }

    
    public function destroy($id)
    {
        $answer = Answer::find($id);
        if (!$answer) {
            return response()->json(['message' => 'الخيار غير موجود'], 404);
        }

        $answer->delete();
        return response()->json(['message' => 'تم حذف الخيار']);
    }

    
    public function adminGetAllAnswers(Request $request)
    {
        $query = Answer::query();

        
        if ($request->has('question_id')) {
            $query->where('question_id', $request->question_id);
        }

       
        if ($request->has('is_correct')) {
            $query->where('is_correct', filter_var($request->is_correct, FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = $request->get('per_page', 15); 
        $answers = $query->paginate($perPage);

        return AnswerResource::collection($answers);
    }

   
    public function adminGetAnswersByQuestion($questionId)
    {
        $question = Question::find($questionId);
        if (!$question) {
            return response()->json(['message' => 'السؤال غير موجود'], 404);
        }

       
        $answers = $question->answers()->get();
        return AnswerResource::collection($answers);
    }

   
    public function adminShowAnswer($id)
    {
        $answer = Answer::find($id);
        if (!$answer) {
            return response()->json(['message' => 'الخيار غير موجود'], 404);
        }

        return new AnswerResource($answer);
    }
}
