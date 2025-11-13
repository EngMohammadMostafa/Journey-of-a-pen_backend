<?php

namespace App\Http\Controllers;

use App\Models\Puy;
use Illuminate\Http\Request;

class PuyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->user_type == 2) {
            $items = Puy::with('user')->get();
        } else {
            $items = Puy::where('user_id', $user->id)->get();
        }
        return response()->json(['payments'=>$items]);
    }

    // يمكن أن يستدعيه webhook من Stripe لتسجيل حالة الدفع
    public function storeFromWebhook(Request $request)
    {
        // توقع البايمنت من Stripe → تخزينه
        // هنا تحتاج أن تنفذ تحقق التواقيع (signature) عند الربط الحقيقي
        $data = $request->all();
        $p = Puy::create([
            'status' => $data['status'] ?? 'pending',
            'date' => now(),
            'user_id' => $data['user_id'] ?? null,
        ]);
        return response()->json(['stored'=>$p], 201);
    }
}
