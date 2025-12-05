<?php

namespace App\Http\Controllers;

use App\Models\Purchasing;
use App\Models\ReadingPlatformUser;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PurchasingController extends Controller
{
    
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->user_type == 2) {
            $purchases = Purchasing::with('users','books')->get();
        } else {
            $purchases = $user->purchases()->with('books')->get();
        }
        return response()->json(['purchases'=>$purchases]);
    }

    
    public function createPurchase(Request $request)
    {
       
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'book_ids' => 'required|array|min:1',
            'book_ids.*' => 'integer|exists:books,id',
            'purchase_date' => 'sometimes|date'
        ]);

        if ($validator->fails()) return response()->json(['errors'=>$validator->errors()], 422);

        $purchase = Purchasing::create([
            'purchase_date' => $request->purchase_date ?? now()->toDateString()
        ]);

       
        $user->purchases()->attach($purchase->id);

        
        foreach ($request->book_ids as $bookId) {
           
            $user->books()->syncWithoutDetaching([$bookId]);
        }

        
        $user->increment('purchases_count', count($request->book_ids));

        return response()->json(['message'=>'تم إنشاء عملية الشراء','purchase'=>$purchase], 201);
    }

   
    public function show($id)
    {
        $purchase = Purchasing::with('users','books')->find($id);
        if (!$purchase) return response()->json(['message'=>'الشراء غير موجود'], 404);
        return response()->json(['purchase'=>$purchase]);
    }
}
