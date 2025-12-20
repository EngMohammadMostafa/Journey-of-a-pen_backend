<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\CompetitionBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompetitionBookController extends Controller
{
    /**
     * مشاركة المستخدم في المسابقة
     * يسمح برفع كتاب فقط إذا كانت المسابقة فعالة وحالياً ضمن تواريخها
     */
    public function store(Request $request, $competitionId)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        // التحقق من صحة البيانات
        try {
            $request->validate([
                'title' => 'required|string|max:50',
                'file'  => 'required|file|mimes:pdf|max:20480',
            ], [
                'title.required' => 'الرجاء وضع كافة البيانات المطلوبة',
                'file.required'  => 'الرجاء وضع كافة البيانات المطلوبة',
                'file.mimes'     => 'الرجاء رفع ملف نوعه PDF',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->errors() ? implode('، ', array_map(fn($v) => $v[0], $e->errors())) : 'خطأ في البيانات'
            ], 422);
        }

        // التحقق من المشاركة السابقة
        $alreadyParticipated = CompetitionBook::where('competition_id', $competitionId)
            ->where('user_id', $user->id)
            ->exists();
        if ($alreadyParticipated) {
            return response()->json(['message' => 'لقد قمت بالمشاركة سابقًا في هذه المسابقة'], 409);
        }

        // التحقق من حالة المسابقة وتواريخها
        $competition = Competition::find($competitionId);
        if (!$competition || $competition->status !== 'active' || now()->lt($competition->startdate) || now()->gt($competition->enddate)) {
            return response()->json(['message' => 'المسابقة غير متاحة حالياً'], 403);
        }

        // التحقق من الحد الأقصى للمشاركين
        $participantsCount = CompetitionBook::where('competition_id', $competitionId)->count();
        if ($participantsCount >= $competition->max_user) {
            return response()->json(['message' => 'لا يمكنك المشاركة، لقد اكتمل عدد المشاركين'], 403);
        }

        // رفع الكتاب
        $file = $request->file('file');
        $path = $file->store('competition_books');

        $book = CompetitionBook::create([
            'competition_id' => $competitionId,
            'user_id'        => $user->id,
            'title'          => $request->title,
            'file_path'      => $path,
            'file_type'      => 'pdf',
            'file_size'      => $file->getSize(),
            'likes_count'    => 0,
        ]);

        return response()->json([
            'message' => 'تم رفع الكتاب للمسابقة بنجاح',
            'book' => $book
        ], 201);
    }

    /**
     * عرض كتب المسابقة للمستخدم العادي
     * يسمح بعرض الكتب فقط إذا كانت المسابقة فعالة وحالياً ضمن تواريخها
     */
    public function index($competitionId)
    {
        $competition = Competition::findOrFail($competitionId);

        if ($competition->status !== 'active' || now()->lt($competition->startdate) || now()->gt($competition->enddate)) {
            return response()->json([
                'message' => 'المسابقة غير متاحة حالياً'
            ], 403);
        }

        $books = CompetitionBook::where('competition_id', $competitionId)
            ->orderByDesc('likes_count')
            ->select('competition_book_id', 'title', 'likes_count')
            ->get();

        return response()->json(['success' => true, 'books' => $books]);
    }

    /**
     * لايك / إلغاء لايك على كتاب
     * يسمح فقط إذا كانت المسابقة فعالة
     */
    public function like(Request $request, $id)
    {
        $user = $request->user();
        $book = CompetitionBook::findOrFail($id);

        $competition = Competition::findOrFail($book->competition_id);
        if ($competition->status !== 'active' || now()->lt($competition->startdate) || now()->gt($competition->enddate)) {
            return response()->json([
                'message' => 'المسابقة انتهت، لا يمكن التفاعل مع الكتاب'
            ], 403);
        }

        $result = $book->likedUsers()->toggle($user->id);
        $book->likes_count = $book->likedUsers()->count();
        $book->save();

        return response()->json([
            'liked' => in_array($user->id, $result['attached']),
            'likes_count' => $book->likes_count
        ]);
    }

    /**
     * حذف كتاب مستخدم من المسابقة (للأدمن فقط)
     */
    public function destroy(Request $request, $bookId)
    {
        $user = $request->user();
        if (!isset($user->user_type) || $user->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $book = CompetitionBook::findOrFail($bookId);

        // حذف اللايكات
        DB::table('competition_book_user')
            ->where('user_id', $book->user_id)
            ->whereIn('competition_book_id', function($query) use ($book) {
                $query->select('competition_book_id')
                    ->from('competition_books')
                    ->where('competition_id', $book->competition_id);
            })
            ->delete();

        // حذف الملف الفعلي
        Storage::delete($book->file_path);

        // حذف الكتاب نفسه
        $book->delete();

        return response()->json([
            'message' => 'تم حذف مشاركة المستخدم من المسابقة وكل اللايكات المرتبطة بها'
        ]);
    }

    /**
     * تحميل كتاب المسابقة
     * يسمح فقط إذا كانت المسابقة فعالة
     */
    public function download($id)
    {
        $book = CompetitionBook::findOrFail($id);
        $competition = Competition::findOrFail($book->competition_id);

        if ($competition->status !== 'active' || now()->lt($competition->startdate) || now()->gt($competition->enddate)) {
            return response()->json([
                'message' => 'المسابقة انتهت، لا يمكن تحميل الكتاب'
            ], 403);
        }

        if (!Storage::exists($book->file_path)) {
            return response()->json(['message' => 'الملف غير موجود'], 404);
        }

        return response()->download(storage_path('app/' . $book->file_path), $book->title . '.pdf');
    }

    /**
     * عرض الكتب مع أسماء المستخدمين الذين أعجبوا بها (للأدمن)
     */
    public function adminLikes($competitionId)
    {
        $books = CompetitionBook::where('competition_id', $competitionId)
            ->with('likedUsers:id,name')
            ->get();

        return response()->json([
            'success' => true,
            'books' => $books
        ]);
    }

    /**
     * إضافة كتاب فائز للمنصة (للأدمن)
     */
    public function addToPlatform($bookId)
    {
        $book = CompetitionBook::findOrFail($bookId);
        return response()->json([
            'message' => 'تم إضافة الكتاب للمنصة بنجاح',
            'book' => $book
        ]);
    }
}

