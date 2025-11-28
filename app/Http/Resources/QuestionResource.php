<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\AnswerResource;

class QuestionResource extends JsonResource
{
    /**
     * تحويل نموذج السؤال إلى مصفوفة JSON
     * نحمِل الإجابات باستخدام AnswerResource (وبالتالي تتحكم في is_correct)
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'question_text' => $this->question_text,
            'book_id' => $this->book_id,
            'created_at' => $this->created_at,
            'answers' => AnswerResource::collection($this->whenLoaded('answers')),
        ];
    }
}
