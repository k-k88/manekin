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
                'store_id'      => $requestModel->store_id,
                'start_time'    => $requestModel->is_day_off ? null : $requestModel->start_time,
                'end_time'      => $requestModel->is_day_off ? null : $requestModel->end_time,
                'is_day_off'    => (int) $requestModel->is_day_off,
                'is_paid_leave' => (int) $requestModel->is_paid_leave ?? false,
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
                    'store_id'      => $req->store_id,
                    'start_time'    => $req->is_day_off ? null : $req->start_time,
                    'end_time'      => $req->is_day_off ? null : $req->end_time,
                    'is_day_off'    => (int) $req->is_day_off,
                    'is_paid_leave' => (int) $req->is_paid_leave ?? false,
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

    // ===============================
    // 元の save メソッド（残す）
    // ===============================
 public function save(Request $request, $companyId)
{
    $userId = $request->user_id;
    $date   = Carbon::parse(substr($request->date, 0, 10))->format('Y-m-d');

    $shift = Shift::where('user_id', $userId)
        ->where('shift_date', $date)
        ->first();

    if (!$shift) {
        $shift = new Shift();
    }

    $targetUser = \App\Models\User::find($userId);

    $shift->user_id    = $userId;
    $shift->store_id   = $targetUser->store_id;
    $shift->shift_date = $date;
    $shift->status     = 'approved';

    $isDayOff    = $request->boolean('is_day_off', false);
    $isPaidLeave = $request->boolean('is_paid_leave', false);

    if ($isPaidLeave) {
        // 有休
        $shift->is_day_off    = $isDayOff;  // 元の休みフラグは維持
        $shift->is_paid_leave = true;
        $shift->start_time    = null;
        $shift->end_time      = null;

    } elseif ($isDayOff) {
        // 休み
        $shift->is_day_off    = true;
        $shift->is_paid_leave = false;
        $shift->start_time    = null;
        $shift->end_time      = null;

    } else {
        // 出勤
        $shift->is_day_off    = false;
        $shift->is_paid_leave = false;

        $start = $request->start_time ? substr($request->start_time, -5) : null;
        $end   = $request->end_time   ? substr($request->end_time,   -5) : null;

        $shift->start_time = $start ? Carbon::parse("$date $start:00")->format('Y-m-d H:i:s') : null;
        $shift->end_time   = $end   ? Carbon::parse("$date $end:00")->format('Y-m-d H:i:s') : null;

        if ($shift->start_time && $shift->end_time &&
            Carbon::parse($shift->end_time)->lt(Carbon::parse($shift->start_time))) {

            $shift->end_time = Carbon::parse($shift->end_time)->addDay()->format('Y-m-d H:i:s');
        }
    }

    $shift->save();

    return response()->json(['shift' => $shift]);
}


    // ===============================
    // 拡張 save メソッド（boolean + 有休対応）
    // ===============================
    public function saveExtended(Request $request, $companyId)
    {
        try {
            $userId = $request->user_id;
            $date   = Carbon::parse($request->date)->format('Y-m-d');

            $shift = Shift::firstOrNew([
                'user_id'    => $userId,
                'shift_date' => $date
            ]);

            $user = \App\Models\User::find($userId);
            $shift->user_id    = $userId;
            $shift->store_id   = $user->store_id;
            $shift->shift_date = $date;
            $shift->status     = 'approved';

            // 元の boolean は維持しつつ、有休カラムを追加
            $isDayOff    = $request->boolean('is_day_off', false);       // 希望休
            $isPaidLeave = $request->boolean('is_paid_leave', false);    // 有休

            if ($isPaidLeave) {
                // 有休
                $shift->is_day_off    = $isDayOff; // 元の値は壊さない
                $shift->is_paid_leave = true;
                $shift->start_time    = null;
                $shift->end_time      = null;
            } elseif ($isDayOff) {
                // 希望休
                $shift->is_day_off    = true;
                $shift->is_paid_leave = false;
                $shift->start_time    = null;
                $shift->end_time      = null;
            } else {
                // 出勤
                $shift->is_day_off    = false;
                $shift->is_paid_leave = false;
                [$shift->start_time, $shift->end_time] = $this->parseShiftTimes(
                    $date,
                    $request->start_time,
                    $request->end_time
                );
            }

            $shift->save();
            return response()->json(['shift' => $shift]);

        } catch (\Exception $e) {
            \Log::error('シフト保存エラー: '.$e->getMessage(), $request->all());
            return response()->json(['status'=>'error','message'=>$e->getMessage()]);
        }
    }

    // ===============================
    // 時刻変換 / 深夜跨ぎ対応
    // ===============================
    private function parseShiftTimes($baseDate, $start, $end)
    {
        $startTime = $this->normalizeTime($baseDate, $start);
        $endTime   = $this->normalizeTime($baseDate, $end);

        if ($endTime && $startTime && $endTime->lt($startTime)) {
            $endTime->addDay();
        }

        return [
            $startTime ? $startTime->format('Y-m-d H:i:s') : null,
            $endTime ? $endTime->format('Y-m-d H:i:s') : null,
        ];
    }

    private function normalizeTime($baseDate, $time)
    {
        if (empty($time)) return null;
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) return null;

        $hour = (int)$m[1];
        $minute = (int)$m[2];
        $date = Carbon::parse($baseDate);

        if ($hour >= 24) {
            $date->addDay();
            $hour -= 24;
        }

        return $date->setTime($hour, $minute);
    }

    // 以下、その他のメソッドは元のまま
    public function deletePage(Company $company)
    {
        return view('company.shift.delete', compact('company'));
    }

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

    public function calendar(Request $request, Company $company)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $year  = substr($month, 0, 4);
        $mon   = substr($month, 5, 2);
        $firstDay = Carbon::create($year, $mon, 1);
        $lastDay  = $firstDay->copy()->endOfMonth();

        $start = $firstDay->copy()->startOfWeek(Carbon::SUNDAY);
        $end   = $lastDay->copy()->endOfWeek(Carbon::SATURDAY);

        $confirmed = Shift::whereHas('user', fn($q) => $q->where('company_id', $company->id))
            ->whereBetween('shift_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->with('user')
            ->get()
            ->groupBy(fn($shift) => Carbon::parse($shift->shift_date)->format('Y-m-d'));

        return view('company.shift.calendar', compact(
            'company', 'confirmed', 'month', 'year', 'mon', 'firstDay', 'lastDay', 'start', 'end'
        ));
    }

    public function calendarSave(Request $request, Company $company)
{
    try {
        $userId  = $request->input('user_id');
        $storeId = $request->input('store_id');
        $date    = substr($request->input('shift_date'), 0, 10);

        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'ユーザーIDがありません']);
        }

        // boolean で取得
        $isDayOff    = $request->boolean('is_day_off', false);       // 希望休
        $isPaidLeave = $request->boolean('is_paid_leave', false);    // 有休

        $startTime = null;
        $endTime   = null;

        if (!$isDayOff && !$isPaidLeave) {
            // 出勤の場合のみ start/end を設定
            $start = $request->input('start_time') ? substr($request->input('start_time'), -5) : null;
            $end   = $request->input('end_time')   ? substr($request->input('end_time'),   -5) : null;

            if ($start) $startTime = "$date $start:00";
            if ($end)   $endTime   = "$date $end:00";
        }

        // DB に保存（boolean → 1/0 で確実に保存）
        $shiftModel = Shift::updateOrCreate(
            [
                'user_id'    => $userId,
                'shift_date' => $date,
                'store_id'   => $storeId,
            ],
            [
                'start_time'    => $startTime,
                'end_time'      => $endTime,
                'is_day_off'    => $isDayOff ? 1 : 0,
                'is_paid_leave' => $isPaidLeave ? 1 : 0,
                'status'        => 'approved',
            ]
        );

        return response()->json([
            'status'   => 'success',
            'shift_id' => $shiftModel->id,
            'message'  => 'シフトが保存されました'
        ]);

    } catch (\Exception $e) {
        \Log::error('シフト保存エラー: ' . $e->getMessage(), $request->all());
        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage()
        ]);
    }
}


    public function getRequestsByDate($companyId, $date)
    {
        $requests = ShiftRequest::with('user')
            ->whereHas('user', fn($q) => $q->where('company_id', $companyId))
            ->where('shift_date', $date)
            ->get()
            ->map(fn($s) => [
                'id'           => $s->id,
                'user_id'      => $s->user_id,
                'user_name'    => $s->user->name,
                'start_time'   => $s->start_time,
                'end_time'     => $s->end_time,
                'is_day_off'   => (int) $s->is_day_off,
                'is_paid_leave'=> (int) $s->is_paid_leave ?? 0,
                'status'       => $s->status,
            ]);

        return response()->json($requests);
    }

    public function show(Company $company, $id)
    {
        $shift = Shift::whereHas('user', fn($q) => $q->where('company_id', $company->id))
            ->with('user')
            ->findOrFail($id);

        return response()->json([
            'id'           => $shift->id,
            'user_id'      => $shift->user_id,
            'user_name'    => $shift->user->name,
            'shift_date'   => $shift->shift_date,
            'start_time'   => $shift->start_time ? Carbon::parse($shift->start_time)->format('H:i') : null,
            'end_time'     => $shift->end_time   ? Carbon::parse($shift->end_time)->format('H:i') : null,
            'is_day_off'   => $shift->is_day_off,
            'is_paid_leave'=> $shift->is_paid_leave ?? false,
        ]);
    }

    public function destroy(Company $company, $id)
    {
        $shift = Shift::whereHas('user', fn($q) => $q->where('company_id', $company->id))
            ->findOrFail($id);

        $shift->delete();
        return response()->json(['success' => true]);
    }
}
