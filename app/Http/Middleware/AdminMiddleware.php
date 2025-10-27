<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // التحقق من أن المستخدم مسجل دخول
        if (!$request->user()) {
            return response()->json([
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }

        // التحقق من أن المستخدم هو أدمن (user_type = 2)
        if ($request->user()->user_type != 2) {
            return response()->json([
                'message' => 'غير مصرح - هذه الوظيفة للإداري فقط'
            ], 403);
        }

        // إذا كان أدمن، اسمحي له بالمرور
        return $next($request);
    }
}