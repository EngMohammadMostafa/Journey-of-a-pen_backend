<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\AnswerResource; // نستخدم Resource لعرض النتائج بشكل موحّد

class AnswerController extends Controller
{
    /**
     * Admin: إضافة إجابة لسؤال
     * Route example: POST /api/admin/questions/{questionId}/answers
     * - يتحقق من وجود السؤال
     * - ينشئ الإجابة (is_correct ممكن تزويده أو لا)
     * - يعيد AnswerResource (سيسمح بعرض is_correct فقط للأدمن)
     */
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

        // نُعيد المورد بدل النموذج الخام لضمان اتساق العرض
        return response()->json([
            'message' => 'تم إنشاء خيار',
            'answer' => new AnswerResource($answer)
        ], 201);
    }

    /**
     * Admin: تعديل إجابة
     * Route example: PUT /api/admin/answers/{id}
     * - يحدث الحقول المرسلة فقط
     * - يعيد AnswerResource محدث
     */
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

    /**
     * Admin: حذف إجابة
     * Route example: DELETE /api/admin/answers/{id}
     */
    public function destroy($id)
    {
        $answer = Answer::find($id);
        if (!$answer) {
            return response()->json(['message' => 'الخيار غير موجود'], 404);
        }

        $answer->delete();
        return response()->json(['message' => 'تم حذف الخيار']);
    }
}
