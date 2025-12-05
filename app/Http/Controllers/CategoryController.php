<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
   
    public function index()
    {
        $categories = Category::all();
        return response()->json(['success' => true, 'data' => $categories]);
    }

    
    public function show($id)
    {
        $category = Category::find($id);
        if (! $category) {
            return response()->json(['message' => 'القسم غير موجود'], 404);
        }
        return response()->json(['success' => true, 'category' => $category]);
    }

    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category = Category::create([
            'name' => $request->name
        ]);

        return response()->json(['success' => true, 'category' => $category], 201);
    }

    
    public function destroy($id)
    {
        $category = Category::find($id);
        if (! $category) {
            return response()->json(['message' => 'القسم غير موجود'], 404);
        }

        
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف القسم وجميع الكتب المرتبطة به بنجاح'
        ]);
    }
}
