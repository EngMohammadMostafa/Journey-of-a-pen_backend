<?php

namespace App\Http\Controllers;

use App\Models\Reward;
use App\Models\ReadingPlatformUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RewardController extends Controller
{
    // Admin: انشاء جائزة
    public function store(Request $request)
    {
        // admin middleware
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:20',
            'number_of_points' => 'required|integer|min:1',
            'description' => 'nullable|string|max:255',
            'user_id' => 'nullable|integer|exists:reading_platform_users,id'
        ]);

        if ($validator->fails()) return response()->json(['errors'=>$validator->errors()], 422);

        $reward = Reward::create($validator->validated());

        return response()->json(['message'=>'تم إنشاء الجائزة','reward'=>$reward], 201);
    }

    // عرض الجوائز (عام)
    public function index()
    {
        $rewards = Reward::with('repoints')->get();
        return response()->json(['rewards'=>$rewards]);
    }

    // مستخدم يريد استبدال نقاطه بجائزة (redeem)
    public function redeem(Request $request, $rewardId)
    {
        $user = $request->user();
        $reward = Reward::find($rewardId);
        if (!$reward) return response()->json(['message'=>'الجائزة غير موجودة'], 404);

        if ($user->points < $reward->number_of_points) {
            return response()->json(['message'=>'ليس لديك نقاط كافية'], 403);
        }

        // خصم النقاط
        $user->decrement('points', $reward->number_of_points);

        // ربط الجائزة بالمستخدم (يمكن أن يكون لدينا علاقة مباشرة)
        $reward->user_id = $user->id;
        $reward->save();

        return response()->json(['message'=>'تم استبدال النقاط بالجائزة','reward'=>$reward,'remaining_points'=>$user->points]);
    }
}
