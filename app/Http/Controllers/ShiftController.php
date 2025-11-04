<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ShiftController extends Controller
{
    // ✅ LINEから自動ログイン
    public function loginWithLine($line_user_id)
    {
        $user = User::where('line_user_id', $line_user_id)->first();
        if (!$user) return redirect('/')->with('error', 'ユーザーが見つかりません');
        Auth::login($user);
        return redirect()->route('shift.calendar', ['user' => $user->id]);
    }

    // ✅ カレンダー表示
    public function calendar(User $user, Request $request)
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        $firstDay = Carbon::create($year, $month, 1);
        $lastDay = $firstDay->copy()->endOfMonth();

        $shifts = Shift::where('user_id', $user->id)
            ->whereBetween('shift_date', [$firstDay, $lastDay])
            ->get()
            ->keyBy('shift_date');

        return view('shift.calendar', compact('user', 'year', 'month', 'firstDay', 'lastDay', 'shifts'));
    }

 public function save(Request $request)
{
    try {
        $request->validate([
            'shift_date' => 'required|date',
        ]);

        $date = $request->shift_date;
        $isDayOff = $request->has('is_day_off'); // ← ここ重要！

        // 深夜対応（26:30 → 翌日 02:30）
        $normalize = function ($date, $time) {
            if (!$time) return null;
            [$h, $m] = explode(':', $time);
            if ($h >= 24) {
                $date = date('Y-m-d', strtotime($date . ' +1 day'));
                $h -= 24;
            }
            return $date . ' ' . sprintf('%02d:%02d:00', $h, $m);
        };

        Shift::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'shift_date' => $date,
            ],
            [
                'start_time' => $isDayOff ? null : $normalize($date, $request->start_time),
                'end_time' => $isDayOff ? null : $normalize($date, $request->end_time),
                'is_day_off' => $isDayOff ? 1 : 0,
                'store_id' => Auth::user()->store_id ?? null,
            ]
        );

        return response()->json(['success' => true]);

    } catch (\Throwable $e) {
        \Log::error('Shift Save Error: ' . $e->getMessage());
        return response()->json(['success' => false, 'error' => $e->getMessage()]);
    }
}



    // ✅ 月まとめて保存
public function saveAll(Request $request)
{
    try {
        $normalize = function ($date, $time) {
            if (!$time) return null;
            [$h, $m] = explode(':', $time);
            if ($h >= 24) {
                $date = date('Y-m-d', strtotime($date . ' +1 day'));
                $h -= 24;
            }
            return $date . ' ' . sprintf('%02d:%02d:00', $h, $m);
        };

        foreach ($request->shifts as $date => $shift) {

            $isDayOff = isset($shift['is_day_off']) && $shift['is_day_off'] == 1;

            Shift::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'shift_date' => $date,
                ],
                [
                    'is_day_off' => $isDayOff ? 1 : 0,
                    'start_time' => $isDayOff ? null : $normalize($date, $shift['start_time'] ?? null),
                    'end_time' => $isDayOff ? null : $normalize($date, $shift['end_time'] ?? null),
                    'store_id' => auth()->user()->store_id,
                ]
            );
        }

        return response()->json(['success' => true]);

    } catch (\Throwable $e) {
        \Log::error('Shift SaveAll Error: ' . $e->getMessage());
        return response()->json(['success' => false]);
    }
}

}
