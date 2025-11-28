<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AnswerResource extends JsonResource
{
    /**
     * تحويل نموذج الإجابة إلى مصفوفة JSON
     * نُظهر الحقل is_correct **فقط** إذا كان المستخدم الحالي أدمن (user_type == 2)
     */
    public function toArray($request)
    {
        $data = [
            'id' => $this->id,
            'answer_text' => $this->answer_text, // استخدم الحقل كما في موديلك
            'question_id' => $this->question_id,
            // إذا أردت إرسال تواريخ أو حقول إضافية اعرضها هنا
        ];

        // فقط للأدمن نظهر is_correct
        if ($request->user() && $request->user()->user_type == 2) {
            $data['is_correct'] = (bool) $this->is_correct;
        }

        return $data;
    }
}
