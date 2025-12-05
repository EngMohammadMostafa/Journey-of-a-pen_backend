<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
       
        if (!$request->user()) {
            return response()->json([
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }

       
        if ($request->user()->user_type != 2) {
            return response()->json([
                'message' => 'غير مصرح - هذه الوظيفة للإداري فقط'
            ], 403);
        }

       
        return $next($request);
    }
}