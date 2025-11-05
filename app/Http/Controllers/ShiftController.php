<?php

namespace App\Http\Controllers;

use App\Models\Shift;            // ✅ 確定側
use App\Models\ShiftRequest;     // ✅ 提出側
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ShiftController extends Controller
{
    // ✅ LINEから自動ログイン（既存）
    public function loginWithLine($line_user_id)
    {
        $user = User::where('line_user_id', $line_user_id)->first();
        if (!$user) return redirect('/')->with('error', 'ユーザーが見つかりません');
        Auth::login($user);
        return redirect()->route('shift.calendar', ['user' => $user->id]);
    }

    public function calendar(User $user, Request $request)
{
    $year  = $request->input('year', now()->year);
    $month = $request->input('month', now()->month);

    $firstDay = Carbon::create($year, $month, 1);
    $lastDay  = $firstDay->copy()->endOfMonth();

    // ✅ 確定済みシフト
    $confirmed = Shift::where('user_id', $user->id)
        ->whereBetween('shift_date', [$firstDay, $lastDay])
        ->get()
        ->keyBy(fn($s) => $s->shift_date->toDateString());

    // ✅ 提出中シフト
    $requests = ShiftRequest::where('user_id', $user->id)
        ->whereBetween('shift_date', [$firstDay, $lastDay])
        ->orderBy('created_at', 'desc')
        ->get()
        ->groupBy(fn($s) => $s->shift_date->toDateString());

    return view('shift.calendar', compact(
        'user', 'year', 'month', 'firstDay', 'lastDay', 'confirmed', 'requests'
    ));
}


    // ✅ 1日分の提出保存（仮提出）
    public function save(Request $request)
    {
        try {
            $request->validate([
                'shift_date' => 'required|date',
            ]);

            $date = $request->shift_date;
            $isDayOff = $request->boolean('is_day_off');

            // 深夜時間対応
            $normalize = function ($date, $time) {
                if (!$time) return null;
                [$h, $m] = explode(':', $time);
                $base = Carbon::parse($date.' 00:00:00');
                if ((int)$h >= 24) {
                    $base->addDay();
                    $h -= 24;
                }
                return $base->copy()->setTime($h, $m);
            };

            ShiftRequest::updateOrCreate(
                [
                    'user_id'    => Auth::id(),
                    'shift_date' => $date,
                ],
                [
                    'start_time' => $isDayOff ? null : $normalize($date, $request->start_time),
                    'end_time'   => $isDayOff ? null : $normalize($date, $request->end_time),
                    'is_day_off' => $isDayOff,
                    'store_id'   => Auth::user()->store_id ?? null,
                    'status'     => 'pending',
                ]
            );

            return response()->json(['success' => true]);

        } catch (\Throwable $e) {
            \Log::error('ShiftRequest Save Error: '.$e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    // ✅ 月まとめて保存（提出）
    public function saveAll(Request $request)
    {
        try {
            foreach ($request->shifts as $date => $s) {

                $isDayOff = ($s['is_day_off'] ?? 0) == 1;

                $normalize = function ($date, $time) {
                    if (!$time) return null;
                    [$h, $m] = explode(':', $time);
                    if ($h >= 24) {
                        $date = date('Y-m-d', strtotime($date . ' +1 day'));
                        $h -= 24;
                    }
                    return $date . ' ' . sprintf('%02d:%02d:00', $h, $m);
                };

                ShiftRequest::updateOrCreate(
                    ['user_id' => auth()->id(), 'shift_date' => $date],
                    [
                        'is_day_off' => $isDayOff,
                        'start_time' => $isDayOff ? null : $normalize($date, $s['start_time'] ?? null),
                        'end_time'   => $isDayOff ? null : $normalize($date, $s['end_time'] ?? null),
                        'store_id'   => auth()->user()->store_id,
                        'status'     => 'pending',
                    ]
                );
            }

            return response()->json(['success' => true]);

        } catch (\Throwable $e) {
            \Log::error('Shift SaveAll Error: '.$e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
 

public function requests(Company $company)
{
    // ✅ 会社に所属するユーザーの提出中シフトのみ取得
    $requests = ShiftRequest::whereIn('user_id', function($q) use ($company) {
            $q->select('id')->from('users')->where('company_id', $company->id);
        })
        ->where('status', 'pending') // 未承認のみ
        ->orderBy('shift_date')
        ->get()
        ->groupBy('user_id'); // 従業員ごとにまとめる

    return view('company.shifts.requests', compact('company', 'requests'));
}

}
