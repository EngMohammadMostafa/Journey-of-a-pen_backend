<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * عرض كل الأقسام
     * (يمكنك تركه عام أو وضعه تحت auth حسب حاجتك — هنا عام)
     */
    public function index()
    {
        $categories = Category::all();
        return response()->json(['success' => true, 'data' => $categories]);
    }

    /**
     * عرض قسم واحد حسب الـ ID
     */
    public function show($id)
    {
        $category = Category::find($id);
        if (! $category) {
            return response()->json(['message' => 'القسم غير موجود'], 404);
        }
        return response()->json(['success' => true, 'category' => $category]);
    }

    /**
     * إنشاء قسم جديد (Admin)
     * مسار محمي بـ auth:sanctum و middleware 'admin'
     */
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

    /**
     * حذف قسم (Admin)
     * عند حذف القسم:
     * 1️⃣ سيتم حذف كل الكتب المرتبطة بالقسم
     * 2️⃣ كل كتاب عند حذفه سيحذف أسئلته، إجاباته، وملفاته تلقائيًا (انظر Book::deleting)
     */
    public function destroy($id)
    {
        $category = Category::find($id);
        if (! $category) {
            return response()->json(['message' => 'القسم غير موجود'], 404);
        }

        // حذف القسم + كل الكتب المرتبطة به
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف القسم وجميع الكتب المرتبطة به بنجاح'
        ]);
    }
}
