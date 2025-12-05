<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AnswerResource extends JsonResource
{
    
    public function toArray($request)
    {
        $data = [
            'id' => $this->id,
            'answer_text' => $this->answer_text, 
            'question_id' => $this->question_id,
           
        ];

      
        if ($request->user() && $request->user()->user_type == 2) {
            $data['is_correct'] = (bool) $this->is_correct;
        }

        return $data;
    }
}
