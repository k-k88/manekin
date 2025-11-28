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
    public function loginWithLine($line_user_id)
    {
        $user = User::where('line_user_id', $line_user_id)->first();
        if (!$user) {
            return redirect('/')->with('error', 'ユーザーが見つかりません');
        }

        Auth::login($user);
        return redirect()->route('shift.user.calendar', ['user' => $user->id]);
    }

    /**
     * 🔹 カレンダー表示（従業員側）
     */
    public function calendar(User $user, Request $request)
    {
        $year  = (int)($request->year ?? now()->year);
        $month = (int)($request->month ?? now()->month);

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

        [$deadlinePassed, $lockedDates] = $this->getShiftLockInfo($user, $year, $month);

        return view('shift.calendar', compact(
            'user','year','month','firstDay','lastDay',
            'confirmed','requests','deadlinePassed','lockedDates'
        ));
    }

    /**
     * 🔹 時刻パース
     */
    protected function normalizeTimeString(?string $input): ?array
    {
        if ($input === null || $input === '') return null;

        $input = trim(str_replace(['：',' '], [':',''], $input));

        if (preg_match('/^\d{1,2}$/', $input)) {
            return ['hour' => (int)$input, 'minute' => 0];
        }
        if (preg_match('/^(\d{1,2})(\d{2})$/', $input, $mch)) {
            return ['hour' => (int)$mch[1], 'minute' => (int)$mch[2]];
        }
        if (preg_match('/^(\d{1,2}):(\d{1,2})$/', $input, $mch)) {
            return ['hour' => (int)$mch[1], 'minute' => (int)$mch[2]];
        }

        return null;
    }

    protected function normalizeShiftDateTime(string $baseDate, ?string $time): ?string
    {
        $parsed = $this->normalizeTimeString($time);
        if (!$parsed) return null;

        $h = $parsed['hour'];
        $m = $parsed['minute'];

        $date = Carbon::createFromFormat('Y-m-d H:i', "$baseDate 00:00");
        if ($h >= 24) {
            $date->addDay();
            $h -= 24;
        }

        return $date->setTime($h, $m)->format('Y-m-d H:i:s');
    }

    /**
     * 🔹 シフト締切ロック判定
     */
    protected function getShiftLockInfo(User $user, int $year, int $month): array
    {
        $store = $user->store;
        if (!$store) return [false, []];

        $today      = now()->copy()->startOfDay();
        $targetDate = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd   = $targetDate->copy()->endOfMonth()->day;

        if ($targetDate->lt($today->copy()->startOfMonth())) {
            return [true, range(1, $monthEnd)];
        }

        if ($targetDate->gt($today->copy()->startOfMonth())) {
            return [false, []];
        }

        if (!in_array($store->shift_deadline_type, ['split', 'half'])) {
            $deadline = Carbon::create($year, $month, $store->shift_deadline_day ?? 10)->startOfDay();
            if ($today->gt($deadline)) {
                return [true, range(1, $monthEnd)];
            }
            return [false, []];
        }

        $firstLimit  = Carbon::create($year, $month, $store->shift_first_half_deadline  ?? 10)->startOfDay();
        $secondLimit = Carbon::create($year, $month, $store->shift_second_half_deadline ?? 25)->startOfDay();

        if ($today->lte($firstLimit)) {
            return [false, range(16, $monthEnd)];
        }

        if ($today->gt($firstLimit) && $today->lte($secondLimit)) {
            return [false, range(1, 15)];
        }

        if ($today->gt($secondLimit)) {
            return [true, range(1, $monthEnd)];
        }

        return [false, []];
    }

    /**
     * 🔹 1日保存（有休対応）
     */
    public function save(Request $request)
    {
        try {
            $date     = $request->shift_date;
            $userId   = $request->user_id;

            $isDayOff     = $request->boolean('is_day_off');
            $isPaidLeave  = $request->boolean('is_paid_leave'); // ★追加

            $user = User::findOrFail($userId);
            $baseDate = Carbon::parse($date)->format('Y-m-d');
            $year  = (int)Carbon::parse($date)->year;
            $month = (int)Carbon::parse($date)->month;

            [, $locked] = $this->getShiftLockInfo($user, $year, $month);
            $day = (int)Carbon::parse($date)->day;

            if (in_array($day, $locked)) {
                return response()->json(['success'=>false,'message'=>'⛔ この日は締切済みです'],403);
            }

            if (Shift::where('user_id',$userId)->where('shift_date',$baseDate)->exists()) {
                return response()->json(['success'=>false,'message'=>'⚠ 確定済みです'],409);
            }

            // 有給 → start/end は絶対 null
            if ($isPaidLeave) {
                $start = null;
                $end   = null;
            } else {
                $start = $isDayOff ? null : $this->normalizeShiftDateTime($baseDate,$request->start_time);
                $end   = $isDayOff ? null : $this->normalizeShiftDateTime($baseDate,$request->end_time);

                if (!$isDayOff && (!$start || !$end)) {
                    return response()->json(['success'=>false,'message'=>'時刻が不正です'],422);
                }
            }

            ShiftRequest::updateOrCreate(
                ['user_id'=>$userId,'shift_date'=>$baseDate],
                [
                    'is_day_off'      => $isDayOff,
                    'is_paid_leave'   => $isPaidLeave,   // ★追加
                    'start_time'      => $start,
                    'end_time'        => $end,
                    'store_id'        => $user->store_id,
                    'status'          => 'pending'
                ]
            );

            return response()->json(['success'=>true]);

        } catch (\Throwable $e) {
            \Log::error("Shift save error: ".$e->getMessage());
            return response()->json(['success'=>false],500);
        }
    }

    /**
     * 🔹 一括保存（有休対応）
     */
    public function saveAll(Request $request)
    {
        try {
            $userId = (int)$request->input('user_id');
            $shifts = $request->input('shifts', []);

            $user = User::findOrFail($userId);

            if (empty($shifts)) {
                return response()->json(['success'=>false,'message'=>'シフトが空です'],422);
            }

            $firstKey = array_key_first($shifts);
            $base = Carbon::parse($firstKey);
            $year = (int)$base->year;
            $month = (int)$base->month;

            [, $locked] = $this->getShiftLockInfo($user,$year,$month);

            $monthEnd = Carbon::create($year,$month,1)->endOfMonth()->day;

            for ($d=1; $d <= $monthEnd; $d++) {
                $dateStr = sprintf("%04d-%02d-%02d",$year,$month,$d);

                if (in_array($d,$locked)) continue;

                if (Shift::where('user_id',$userId)->where('shift_date',$dateStr)->exists()) {
                    continue;
                }

                $s = $shifts[$dateStr] ?? null;

                if ($s) {

                    $isDayOff    = !empty($s['is_day_off']);
                    $isPaidLeave = !empty($s['is_paid_leave']); // ★追加

                    if ($isPaidLeave) {
                        $start = null;
                        $end   = null;
                    } else {
                        $start = $isDayOff ? null : $this->normalizeShiftDateTime($dateStr,$s['start_time'] ?? null);
                        $end   = $isDayOff ? null : $this->normalizeShiftDateTime($dateStr,$s['end_time'] ?? null);

                        if (!$isDayOff && (!$start || !$end)) continue;
                    }

                    ShiftRequest::updateOrCreate(
                        ['user_id'=>$userId,'shift_date'=>$dateStr],
                        [
                            'is_day_off'    => $isDayOff,
                            'is_paid_leave' => $isPaidLeave,  // ★追加
                            'start_time'    => $start,
                            'end_time'      => $end,
                            'store_id'      => $user->store_id,
                            'status'        => 'pending'
                        ]
                    );

                } else {

                    ShiftRequest::updateOrCreate(
                        ['user_id'=>$userId,'shift_date'=>$dateStr],
                        [
                            'is_day_off'    => true,
                            'is_paid_leave' => false, // 公休
                            'start_time'    => null,
                            'end_time'      => null,
                            'store_id'      => $user->store_id,
                            'status'        => 'pending'
                        ]
                    );
                }
            }

            return response()->json(['success'=>true]);

        } catch (\Throwable $e) {
            \Log::error('Shift SaveAll Error: '.$e->getMessage());
            return response()->json(['success'=>false,'error'=>$e->getMessage()],500);
        }
    }

    public function requests(Company $company)
    {
        $requests = ShiftRequest::whereIn('user_id', function ($q) use ($company) {
                $q->select('id')
                  ->from('users')
                  ->where('company_id',$company->id)
                  ->whereNull('deleted_at');
            })
            ->orderBy('shift_date')
            ->get()
            ->groupBy('user_id');

        return view('company.shifts.requests', compact('company','requests'));
    }

    protected function isShiftDeadlinePassed(User $user, int $year, int $month): bool
    {
        [$allLocked,] = $this->getShiftLockInfo($user, $year, $month);
        return $allLocked;
    }
}
