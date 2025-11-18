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
    /**
     * 🔹 LINEログイン（自動ログイン）
     */
    public function loginWithLine($line_user_id)
    {
        $user = User::where('line_user_id', $line_user_id)->first();
        if (!$user) return redirect('/')->with('error', 'ユーザーが見つかりません');
        Auth::login($user);
        return redirect()->route('shift.user.calendar', ['user' => $user->id]);
    }

    /**
     * 🔹 カレンダー表示
     */
    public function calendar(User $user, Request $request)
    {
        $year = $request->year ?? now()->year;
        $month = $request->month ?? now()->month;

        $firstDay = Carbon::create($year, $month, 1)->startOfMonth();
        $lastDay  = Carbon::create($year, $month, 1)->endOfMonth();

        $confirmed = Shift::where('user_id', $user->id)
            ->whereBetween('shift_date', [$firstDay, $lastDay])
            ->get()
            ->groupBy(fn($s) => Carbon::parse($s->shift_date)->format('Y-m-d'));

        $requests = ShiftRequest::where('user_id', $user->id)
            ->whereBetween('shift_date', [$firstDay, $lastDay])
            ->get()
            ->groupBy(fn($s) => Carbon::parse($s->shift_date)->format('Y-m-d'));

        // 🔹 締切・ロック日取得
        [$deadlinePassed, $lockedDates] = $this->getShiftLockInfo($user, $year, $month);

        return view('shift.calendar', [
            'user' => $user,
            'year' => $year,
            'month' => $month,
            'firstDay' => $firstDay,
            'lastDay' => $lastDay,
            'confirmed' => $confirmed,
            'requests' => $requests,
            'deadlinePassed' => $deadlinePassed,
            'lockedDates' => $lockedDates,
        ]);
    }

    /**
     * 🔹 シフト提出禁止日を算出（前半・後半対応）
     */
    protected function getShiftLockInfo(User $user, int $year, int $month): array
    {
        $store = $user->store;
        if (!$store) return [false, []];

        $today = now();
        $monthEnd = Carbon::create($year, $month, 1)->endOfMonth();
        $locked = [];

        if ($store->shift_deadline_type === 'single') {
            $deadline = Carbon::create($year, $month, $store->shift_deadline_day ?? 10);
            if ($today->gt($deadline)) {
                $locked = range(1, $monthEnd->day);
            }
        } else { // half
            $firstLimit = Carbon::create($year, $month, $store->shift_first_half_deadline ?? 10);
            $secondLimit = Carbon::create($year, $month, $store->shift_second_half_deadline ?? 25);

            if ($today->gt($firstLimit)) {
                $locked = array_merge($locked, range(1, 15));
            }
            if ($today->gt($secondLimit)) {
                $locked = array_merge($locked, range(16, $monthEnd->day));
            }
        }

        return [$today->gt($monthEnd), array_unique($locked)];
    }

    /**
     * 🔹 1日分保存
     */
    public function save(Request $request)
    {
        try {
            $date = $request->shift_date;
            $userId = $request->user_id;
            $isDayOff = $request->boolean('is_day_off');
            $user = User::findOrFail($userId);

            $month = Carbon::parse($date)->month;
            $year = Carbon::parse($date)->year;

            // 🔹 提出期限チェック（その日のロック含む）
            [$_, $lockedDates] = $this->getShiftLockInfo($user, $year, $month);
            $day = Carbon::parse($date)->day;
            if (in_array($day, $lockedDates)) {
                return response()->json(['success' => false, 'message' => '⛔ この日の提出期限は過ぎています。'], 403);
            }

            if (Shift::where('user_id', $userId)->where('shift_date', $date)->exists()) {
                return response()->json(['success' => false, 'message' => '⚠ この日はすでに確定済みです'], 409);
            }

            $normalize = function ($date, $time) {
                if (!$time) return null;
                [$h, $m] = explode(':', $time);
                $base = Carbon::createFromFormat('Y-m-d H:i', "$date 00:00");
                if ($h >= 24) {
                    $base->addDay();
                    $h -= 24;
                }
                return $base->setTime($h, $m)->format('Y-m-d H:i:s');
            };

            ShiftRequest::updateOrCreate(
                ['user_id' => $userId, 'shift_date' => $date],
                [
                    'is_day_off' => $isDayOff,
                    'start_time' => $isDayOff ? null : $normalize($date, $request->start_time),
                    'end_time'   => $isDayOff ? null : $normalize($date, $request->end_time),
                    'store_id'   => $user->store_id,
                    'status'     => 'pending',
                ]
            );

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            \Log::error("Shift save error: " . $e->getMessage());
            return response()->json(['success' => false], 500);
        }
    }

    /**
     * 🔹 一括保存（1ヶ月分）
     */
    public function saveAll(Request $request)
    {
        try {
            $userId = $request->input('user_id');
            $user = User::findOrFail($userId);

            if (!empty($request->shifts)) {
                $firstKey = array_key_first($request->shifts);
                $date = Carbon::parse($firstKey);
                [$_, $lockedDates] = $this->getShiftLockInfo($user, $date->year, $date->month);
            }

            foreach ($request->shifts as $date => $s) {
                $isDayOff = ($s['is_day_off'] ?? 0) == 1;
                $day = Carbon::parse($date)->day;

                // 🔹 締切日ロック
                if (isset($lockedDates) && in_array($day, $lockedDates)) {
                    continue; // 締切済の日はスキップ
                }

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
                        'store_id'   => $user->store_id,
                        'status'     => 'pending',
                    ]
                );
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            \Log::error('Shift SaveAll Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 🔹 店長側：提出状況一覧
     */
    public function requests(Company $company)
    {
        $requests = ShiftRequest::whereIn('user_id', function ($q) use ($company) {
                $q->select('id')->from('users')->where('company_id', $company->id);
            })
            ->where('status', 'pending')
            ->orderBy('shift_date')
            ->get()
            ->groupBy('user_id');

        return view('company.shifts.requests', compact('company', 'requests'));
    }

    /**
     * 🔹 共通：月単位の提出期限チェック（バックエンド用）
     */
    protected function isShiftDeadlinePassed(User $user, int $year, int $month): bool
    {
        $store = $user->store;
        if (!$store) return false;

        $today = now();
        $targetMonth = Carbon::create($year, $month, 1);

        if ($targetMonth->lt($today->copy()->startOfMonth())) {
            return true; // 過去月
        }

        if ($targetMonth->isSameMonth($today)) {
            $day = $today->day;

            if ($store->shift_deadline_type === 'single') {
                return $day > $store->shift_deadline_day;
            }

            if ($day <= 15) {
                return $day > ($store->shift_first_half_deadline ?? 15);
            } else {
                return $day > ($store->shift_second_half_deadline ?? 30);
            }
        }

        return false; // 未来月
    }
}
