<?php
 
namespace App\Http\Controllers;
 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

use App\Models\User;
use App\Models\Shift;
use App\Models\ShiftRequest;
use App\Models\Company;
 
class ShiftController extends Controller
{
    // ✅ LINEから自動ログイン
    public function loginWithLine($line_user_id)
    {
        $user = User::where('line_user_id', $line_user_id)->first();
        if (!$user) return redirect('/')->with('error', 'ユーザーが見つかりません');
        Auth::login($user);
        return redirect()->route('shift.user.calendar', ['user' => $user->id]);
    }
 
    // ✅ カレンダー表示
// ✅ カレンダー表示
// ✅ カレンダー表示
public function calendar(User $user, Request $request)
{
    $year = $request->year ?? now()->year;
    $month = $request->month ?? now()->month;

    $firstDay = Carbon::create($year, $month, 1)->startOfMonth();
    $lastDay = Carbon::create($year, $month, 1)->endOfMonth();

    $confirmed = Shift::where('user_id', $user->id)
        ->whereDate('shift_date', '>=', $firstDay)
        ->whereDate('shift_date', '<=', $lastDay)
        ->get()
        ->groupBy(fn($s) => Carbon::parse($s->shift_date)->format('Y-m-d'));

    $requests = ShiftRequest::where('user_id', $user->id)
        ->whereDate('shift_date', '>=', $firstDay)
        ->whereDate('shift_date', '<=', $lastDay)
        ->get()
        ->groupBy(fn($s) => Carbon::parse($s->shift_date)->format('Y-m-d'));

    return view('shift.calendar', [
        'user' => $user,
        'year' => $year,
        'month' => $month,
        'firstDay' => $firstDay,     // ← ★ これを追加
        'lastDay' => $lastDay,       // ← ★ これも追加
        'confirmed' => $confirmed,
        'requests' => $requests,
    ]);
}




 
    // ✅ 一時保存（1日分）
   public function save(Request $request)
{
    try {
        $date = $request->shift_date;
        $userId = $request->user_id;
        $isDayOff = $request->boolean('is_day_off');

        // ✅ 既に確定シフトがある場合は提出禁止
        if (Shift::where('user_id', $userId)->where('shift_date', $date)->exists()) {
            return response()->json([
                'success' => false,
                'message' => '⚠ この日はすでに確定済みです'
            ], 409);
        }

        // ✅ 時刻変換関数（24時以降は翌日にする）
        $normalize = function ($date, $time) {
            if (!$time) return null;
            [$h, $m] = explode(':', $time);
            $base = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "$date 00:00");
            if ($h >= 24) {
                $base->addDay();
                $h -= 24;
            }
            return $base->setTime($h, $m)->format('Y-m-d H:i:s');
        };

        // ✅ 提出中シフトを保存（同じ日なら更新 / ないなら作成）
        ShiftRequest::updateOrCreate(
            [
                'user_id' => $userId,
                'shift_date' => $date
            ],
            [
                'is_day_off' => $isDayOff,
                'start_time' => $isDayOff ? null : $normalize($date, $request->start_time),
                'end_time'   => $isDayOff ? null : $normalize($date, $request->end_time),
                'store_id'   => Auth::user()->store_id,
                'status'     => 'pending',
            ]
        );

        return response()->json(['success' => true]);

    } catch (\Throwable $e) {
        \Log::error("Shift save error: ".$e->getMessage());
        return response()->json(['success' => false], 500);
    }
}

 
    // ✅ 一括保存（1ヶ月分）
    public function saveAll(Request $request)
    {
        try {
            $userId = $request->input('user_id');
 
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
                    ['user_id' => $userId, 'shift_date' => $date],
                    [
                        'is_day_off' => $isDayOff,
                        'start_time' => $isDayOff ? null : $normalize($date, $s['start_time'] ?? null),
                        'end_time'   => $isDayOff ? null : $normalize($date, $s['end_time'] ?? null),
                        'store_id'   => User::find($userId)->store_id,
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
 
    // ✅ 店長側：提出状況一覧
    public function requests(Company $company)
    {
        $requests = ShiftRequest::whereIn('user_id', function($q) use ($company) {
                $q->select('id')->from('users')->where('company_id', $company->id);
            })
            ->where('status', 'pending')
            ->orderBy('shift_date')
            ->get()
            ->groupBy('user_id');
 
        return view('company.shifts.requests', compact('company', 'requests'));
    }
}
