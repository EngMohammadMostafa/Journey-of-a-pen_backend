<?php

namespace App\Http\Controllers;

use App\Models\Repoint;
use App\Models\Reward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RepointController extends Controller
{
    // Admin: اضافة سجل نقاط لجوائز
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'number_of_points' => 'required|integer|min:1',
            'reward_id' => 'required|integer|exists:reward,id'
        ]);

        if ($validator->fails()) return response()->json(['errors'=>$validator->errors()], 422);

        $rp = Repoint::create([
            'number_of_points' => $request->number_of_points,
            'reward_id' => $request->reward_id
        ]);

        return response()->json(['message'=>'تم اضافة سجل النقاط','repoint'=>$rp], 201);
    }

    // عرض سجلات نقاط لجوائز معينة أو كلها
    public function index()
    {
        $items = Repoint::with('reward')->get();
        return response()->json(['repoints'=>$items]);
    }
}
