<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\ShiftRequest;
use App\Models\Company;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ShiftApprovalController extends Controller
{
    // 承認一覧（提出シフト）
    public function index(Company $company, Request $request)
    {
        $month  = $request->input('month', now()->format('Y-m'));
        $userId = $request->input('user_id');

        $q = ShiftRequest::whereHas('user', fn($q) =>
            $q->where('company_id', $company->id)
        )
        ->where('status', 'pending')
        ->where('shift_date', 'like', "$month%")
        ->with('user')
        ->orderBy('shift_date');

        if ($userId) {
            $q->where('user_id', $userId);
        }

        return view('company.shift.requests', [
            'company'  => $company,
            'requests' => $q->get(),
            'users'    => $company->users()->get(),
            'month'    => $month,
            'userId'   => $userId,
        ]);
    }

    // 個別承認
    public function approve(Company $company, ShiftRequest $requestModel)
    {
        if ($requestModel->user->company_id !== $company->id) abort(403);

        Shift::updateOrCreate(
            ['user_id' => $requestModel->user_id, 'shift_date' => $requestModel->shift_date],
            [
                'store_id'   => $requestModel->store_id,
                'start_time' => $requestModel->is_day_off ? null : $requestModel->start_time,
                'end_time'   => $requestModel->is_day_off ? null : $requestModel->end_time,
                'is_day_off' => (int) $requestModel->is_day_off,
            ]
        );

        $requestModel->update(['status' => 'approved']);

        return back()->with('success', 'シフトリクエストを承認しました。');
    }

    // 却下
    public function reject(Company $company, ShiftRequest $requestModel)
    {
        if ($requestModel->user->company_id !== $company->id) abort(403);

        $requestModel->update(['status' => 'rejected']);
        return back()->with('success', 'シフトリクエストを却下しました。');
    }

    // 一括承認
    public function approveAll(Company $company, Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));

        $list = ShiftRequest::whereHas('user', fn($q) =>
            $q->where('company_id', $company->id)
        )
        ->where('status', 'pending')
        ->where('shift_date', 'like', "$month%")
        ->get();

        foreach ($list as $req) {
            Shift::updateOrCreate(
                ['user_id' => $req->user_id, 'shift_date' => $req->shift_date],
                [
                    'store_id'   => $req->store_id,
                    'start_time' => $req->is_day_off ? null : $req->start_time,
                    'end_time'   => $req->is_day_off ? null : $req->end_time,
                    'is_day_off' => (int) $req->is_day_off,
                ]
            );
            $req->update(['status' => 'approved']);
        }

        return back()->with('success', '当月の承認待ちを一括承認しました。');
    }

    // シフト編集画面（確定処理用）
    public function editAll(Request $request, Company $company)
    {
        $month = $request->input('month', now()->format('Y-m'));

        $shiftRequests = ShiftRequest::whereHas('user', fn($q) =>
            $q->where('company_id', $company->id)
        )
        ->where('shift_date', 'like', "$month%")
        ->with('user')
        ->orderBy('shift_date')
        ->orderBy('user_id')
        ->get();

        return view('company.shift.edit', compact('company', 'shiftRequests', 'month'));
    }

// シフト保存処理（save メソッド内）
public function save(Request $request, $companyId)
{
    $userId  = $request->input('user_id');
    $date    = $request->input('date');
    $start   = $request->input('start_time');
    $end     = $request->input('end_time');
    $shiftId = $request->input('shift_id');

    // 秒をカット
    $startTime = $start ? substr($start, 0, 5) : null;
    $endTime   = $end ? substr($end, 0, 5) : null;

    // フル日時に変換
    $startDateTime = $startTime ? $date . ' ' . $startTime : null;
    $endDateTime   = $endTime ? $date . ' ' . $endTime : null;

    // 翌日扱い
    if ($startTime && $endTime && $endTime < $startTime) {
        $endDateTime = date('Y-m-d H:i', strtotime($endDateTime . ' +1 day'));
    }

    // ユーザーの店舗ID
    $user = \App\Models\User::find($userId);
    $storeId = $user ? $user->store_id : \App\Models\Store::where('company_id', $companyId)->value('id');

    if ($shiftId) {
        $shift = \App\Models\Shift::find($shiftId);
        if ($shift) {
            $shift->update([
                'store_id'   => $storeId,
                'shift_date' => $date,
                'start_time' => $startDateTime,
                'end_time'   => $endDateTime,
                'is_day_off' => $startTime && $endTime ? 0 : 1,
            ]);
        }
    } else {
        $shift = \App\Models\Shift::updateOrCreate(
            ['user_id' => $userId, 'shift_date' => $date],
            [
                'company_id' => $companyId,
                'store_id'   => $storeId,
                'start_time' => $startDateTime,
                'end_time'   => $endDateTime,
                'is_day_off' => $startTime && $endTime ? 0 : 1,
                'status'     => 'pending',
            ]
        );
    }

    // フロント用にユーザー名も含めて返す
    return response()->json([
        'message'   => '保存しました',
        'shift'     => [
            'id'         => $shift->id,
            'user_id'    => $shift->user_id,
            'user_name'  => $shift->user->name,
            'shift_date' => $shift->shift_date,
            'start_time' => $shift->start_time ? substr($shift->start_time, 11, 5) : null,
            'end_time'   => $shift->end_time ? substr($shift->end_time, 11, 5) : null,
            'is_day_off' => $shift->is_day_off,
        ],
    ]);
}


 /* 時刻文字列（例："26:00"）をCarbonの日時に変換
 * 翌日にまたがる時間も処理可能
 */
private function parseShiftTimes($baseDate, $start, $end)
{
    $startTime = $this->normalizeTime($baseDate, $start);
    $endTime   = $this->normalizeTime($baseDate, $end);

    // もし終了時刻が開始より早い（深夜をまたぐ）場合 → 翌日に加算
    if ($endTime && $startTime && $endTime->lt($startTime)) {
        $endTime->addDay();
    }

    return [
        $startTime ? $startTime->format('Y-m-d H:i:s') : null,
        $endTime ? $endTime->format('Y-m-d H:i:s') : null,
    ];
}

/**
 * "25:00" などの時間も正しく扱うための補助関数
 */
private function normalizeTime($baseDate, $time)
{
    if (empty($time)) return null;
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) return null;

    $hour = (int)$m[1];
    $minute = (int)$m[2];
    $date = Carbon::parse($baseDate);

    if ($hour >= 24) {
        // 翌日に繰り上げ（例：25:00 → 翌日1:00）
        $date->addDay();
        $hour -= 24;
    }

    return $date->setTime($hour, $minute);
}





    public function deletePage(Company $company)
    {
        return view('company.shift.delete', compact('company'));
    }

    // シフト削除処理
    public function delete(Request $request, Company $company)
    {
        $request->validate([
            'month' => 'required|date_format:Y-m'
        ]);

        Shift::where('company_id', $company->id)
            ->whereRaw("DATE_FORMAT(shift_date, '%Y-%m') = ?", [$request->month])
            ->delete();

        return redirect()->route('company.dashboard', $company->id)
            ->with('success', 'シフトを削除しました');
    }

    // カレンダー表示
    public function calendar(Request $request, Company $company)
    {
        $month = $request->input('month', now()->format('Y-m'));

        $year = substr($month, 0, 4);
        $mon  = substr($month, 5, 2);
        $firstDay = Carbon::create($year, $mon, 1);
        $lastDay  = $firstDay->copy()->endOfMonth();

        $start = $firstDay->copy()->startOfWeek(Carbon::SUNDAY);
        $end   = $lastDay->copy()->endOfWeek(Carbon::SATURDAY);

        $confirmed = Shift::whereHas('user', fn($q) => $q->where('company_id', $company->id))
            ->whereBetween('shift_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->with('user')
            ->get()
            ->groupBy('shift_date');

        return view('company.shift.calendar', compact(
            'company', 'confirmed', 'month', 'year', 'mon', 'firstDay', 'lastDay', 'start', 'end'
        ));
    }

    // カレンダー保存（API用）
    public function calendarSave(Request $request, Company $company)
    {
        try {
            $userId    = $request->input('user_id');
            $storeId   = $request->input('store_id');
            $shiftDate = $request->input('shift_date', now()->format('Y-m-d'));

            if (!$userId) {
                return response()->json(['status' => 'error', 'message' => 'ユーザーIDがありません']);
            }

            $startTime = $request->input('start_time') 
                ? Carbon::parse($request->input('start_time'))->format('Y-m-d H:i:s') 
                : null;

            $endTime = $request->input('end_time') 
                ? Carbon::parse($request->input('end_time'))->format('Y-m-d H:i:s') 
                : null;

            $isDayOff = $request->input('is_day_off', 0);

            $shiftModel = Shift::updateOrCreate(
                [
                    'user_id'    => $userId,
                    'shift_date' => $shiftDate,
                    'store_id'   => $storeId,
                ],
                [
                    'start_time' => $startTime,
                    'end_time'   => $endTime,
                    'is_day_off' => $isDayOff,
                ]
            );

            return response()->json([
                'status' => 'success',
                'shift_id' => $shiftModel->id,
                'message' => 'シフトが保存されました'
            ]);

        } catch (\Exception $e) {
            \Log::error('シフト保存エラー: ' . $e->getMessage(), $request->all());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    // 日付ごとのシフトリクエスト取得（API用）
   // ShiftApprovalController
public function getRequestsByDate($companyId, $date)
{
    $requests = ShiftRequest::with('user')
        ->whereHas('user', fn($q) => $q->where('company_id', $companyId))
        ->where('shift_date', $date)
        ->get()
        ->map(fn($s) => [
            'id'         => $s->id,
            'user_id'    => $s->user_id,      // ここ必須
            'user_name'  => $s->user->name,
            'start_time' => $s->start_time,
            'end_time'   => $s->end_time,
            'is_day_off' => (int) $s->is_day_off,
        ]);
    return response()->json($requests);
}

public function show($companyId, $shiftId)
{
    $shift = \App\Models\Shift::with('user')->findOrFail($shiftId);

    return response()->json([
        'id'          => $shift->id,
        'user_id'     => $shift->user_id,
        'user_name'   => $shift->user->name ?? '',
        'shift_date'  => $shift->shift_date,
        'start_time'  => $shift->start_time,
        'end_time'    => $shift->end_time,
        'is_day_off'  => $shift->is_day_off,
        'status'      => $shift->status,
    ]);
}

public function destroy($companyId, $shiftId)
{
    $shift = \App\Models\Shift::find($shiftId);
    if (!$shift) {
        return response()->json(['success' => false, 'message' => 'シフトが見つかりません。']);
    }

    $shift->delete();

    return response()->json(['success' => true]);
}


}
