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
     * Route: POST /api/admin/questions/{questionId}/answers
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

        return response()->json([
            'message' => 'تم إنشاء خيار',
            'answer' => new AnswerResource($answer)
        ], 201);
    }

    /**
     * Admin: تعديل إجابة
     * Route: PUT /api/admin/answers/{id}
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
     * Route: DELETE /api/admin/answers/{id}
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

    /**
     * Admin: جلب كل الإجابات مع دعم Pagination وفلترة عبر query params
     * Route: GET /api/admin/answers
     * Query params:
     *  - per_page: عدد النتائج في الصفحة الواحدة (افتراضي 15)
     *  - page: رقم الصفحة
     *  - question_id: فلترة حسب سؤال معين (اختياري)
     *  - is_correct: فلترة حسب الإجابات الصحيحة أو الخاطئة (اختياري)
     */
    public function adminGetAllAnswers(Request $request)
    {
        $query = Answer::query();

        // فلترة حسب السؤال إذا تم تمريره
        if ($request->has('question_id')) {
            $query->where('question_id', $request->question_id);
        }

        // فلترة حسب is_correct إذا تم تمريره
        if ($request->has('is_correct')) {
            $query->where('is_correct', filter_var($request->is_correct, FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = $request->get('per_page', 15); // العدد الافتراضي لكل صفحة
        $answers = $query->paginate($perPage);

        return AnswerResource::collection($answers);
    }

    /**
     * Admin: جلب كل الإجابات لسؤال محدد
     * Route: GET /api/admin/questions/{questionId}/answers
     * - مناسب لعرض جدول الإجابات داخل صفحة السؤال
     */
    public function adminGetAnswersByQuestion($questionId)
    {
        $question = Question::find($questionId);
        if (!$question) {
            return response()->json(['message' => 'السؤال غير موجود'], 404);
        }

        // جلب جميع الإجابات المرتبطة بالسؤال
        $answers = $question->answers()->get(); // تأكد أن موديل Question يحتوي على relation answers()
        return AnswerResource::collection($answers);
    }

    /**
     * Admin: جلب إجابة واحدة حسب id
     * Route: GET /api/admin/answers/{id}
     * - مناسب لصفحة تعديل إجابة
     */
    public function adminShowAnswer($id)
    {
        $answer = Answer::find($id);
        if (!$answer) {
            return response()->json(['message' => 'الخيار غير موجود'], 404);
        }

        return new AnswerResource($answer);
    }
}
