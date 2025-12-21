<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CompetitionController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->user_type != 1) {
            return response()->json(['message' => 'غير مسموح'], 403);
        }

        $competitions = Competition::where('status', 'active')
            ->where('startdate', '<=', now())
            ->where('enddate', '>=', now())
            ->get();

        return response()->json([
            'success' => true,
            'competitions' => $competitions
        ]);
    }

    public function adminIndex(Request $request)
    {
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        return response()->json([
            'success' => true,
            'competitions' => Competition::all()
        ]);
    }

    public function store(Request $request)
    {
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:50',
            'status' => 'required|in:active,inactive,finished',
            'startdate' => 'required|date',
            'enddate' => 'required|date|after_or_equal:startdate',
            'max_user' => 'required|integer|min:1',
        ]);

        return response()->json([
            'message' => 'تم إنشاء المسابقة',
            'competition' => Competition::create($request->all())
        ], 201);
    }

    public function update(Request $request, $id)
    {
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $competition = Competition::findOrFail($id);
        $competition->update($request->all());

        return response()->json([
            'message' => 'تم التعديل',
            'competition' => $competition
        ]);
    }

    public function destroy(Request $request, $id)
    {
        if ($request->user()->user_type != 2) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $competition = Competition::findOrFail($id);

        foreach ($competition->competitionBooks as $book) {
            DB::table('competition_book_user')
                ->where('competition_book_id', $book->competition_book_id)
                ->delete();

            Storage::delete($book->file_path);
            $book->delete();
        }

        $competition->delete();

        return response()->json(['message' => 'تم الحذف']);
    }
}
