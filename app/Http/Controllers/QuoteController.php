<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuoteController extends Controller
{
    
    public function index()
    {
        $quotes = Quote::latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'تم جلب الاقتباسات بنجاح',
            'quotes' => $quotes,
            'count' => $quotes->count()
        ], 200);
    }

    
    public function store(Request $request)
    {
       
        $request->validate([
            'text' => 'required|string|max:255',       
            'book_name' => 'required|string|max:50'   
        ]);

       
        $quote = Quote::create([
            'text' => $request->text,
            'book_name' => $request->book_name,
            'user_id' => Auth::id()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم نشر الاقتباس بنجاح',
            'quote' => $quote
        ], 201);
    }

   
    public function destroy($id)
    {
        
        if (Auth::user()->user_type !== 2) {
            return response()->json([
                'success' => false,
                'message' => 'غير مسموح - تحتاج صلاحيات أدمن'
            ], 403);
        }

        $quote = Quote::find($id);
        
        
        if (!$quote) {
            return response()->json([
                'success' => false,
                'message' => 'الاقتباس غير موجود'
            ], 404);
        }

        
        $quote->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الاقتباس بنجاح'
        ], 200);
    }
}
