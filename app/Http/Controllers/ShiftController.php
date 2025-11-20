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
        if (!$user) {
            return redirect('/')->with('error', 'ユーザーが見つかりません');
        }

        Auth::login($user);
        return redirect()->route('shift.user.calendar', ['user' => $user->id]);
    }

    /**
     * 🔹 カレンダー表示
     */
    public function calendar(User $user, Request $request)
    {
        $year  = (int)($request->year ?? now()->year);
        $month = (int)($request->month ?? now()->month);

        $firstDay = Carbon::create($year, $month, 1)->startOfMonth();
        $lastDay  = Carbon::create($year, $month, 1)->endOfMonth();

        // 確定シフト
        $confirmed = Shift::where('user_id', $user->id)
            ->whereBetween('shift_date', [$firstDay, $lastDay])
            ->get()
            ->groupBy(fn($s) => Carbon::parse($s->shift_date)->format('Y-m-d'));

        // 提出中シフト
        $requests = ShiftRequest::where('user_id', $user->id)
            ->whereBetween('shift_date', [$firstDay, $lastDay])
            ->get()
            ->groupBy(fn($s) => Carbon::parse($s->shift_date)->format('Y-m-d'));

        // 🔹 締切・ロック日取得
        [$deadlinePassed, $lockedDates] = $this->getShiftLockInfo($user, $year, $month);

        return view('shift.calendar', compact(
            'user',
            'year',
            'month',
            'firstDay',
            'lastDay',
            'confirmed',
            'requests',
            'deadlinePassed',
            'lockedDates'
        ));
    }

    /**
     * 🔹 時刻文字列を 00:00〜29:59 対応で正規化
     *   - 入力例: "9", "900", "9:0", "09:00", "25:30"
     *   - 戻り値: ['date' => 'Y-m-d', 'time' => 'H:i:s'] を作るための情報は normalizeShiftDateTime() 側で使用
     */
    protected function normalizeTimeString(?string $input): ?array
    {
        if ($input === null || $input === '') {
            return null;
        }

        $input = trim(str_replace(['：', ' '], [':', ''], $input));

        // 9   → 09:00
        if (preg_match('/^\d{1,2}$/', $input)) {
            $h = (int)$input;
            $m = 0;
        }
        // 930 → 09:30
        elseif (preg_match('/^(\d{1,2})(\d{2})$/', $input, $mch)) {
            $h = (int)$mch[1];
            $m = (int)$mch[2];
        }
        // 9:0 / 9:30 / 29:30
        elseif (preg_match('/^(\d{1,2}):(\d{1,2})$/', $input, $mch)) {
            $h = (int)$mch[1];
            $m = (int)$mch[2];
        } else {
            return null; // 不正
        }

        if ($h < 0 || $h > 29 || $m < 0 || $m > 59) {
            return null;
        }

        return ['hour' => $h, 'minute' => $m];
    }

    /**
     * 🔹 29:00対応で Y-m-d H:i:s 文字列を作る
     *   - $baseDate: "Y-m-d"
     */
    protected function normalizeShiftDateTime(string $baseDate, ?string $time): ?string
    {
        $parsed = $this->normalizeTimeString($time);
        if ($parsed === null) {
            return null;
        }

        $h = $parsed['hour'];
        $m = $parsed['minute'];

        $date = Carbon::createFromFormat('Y-m-d H:i', "$baseDate 00:00");
        if ($h >= 24) {
            $date->addDay();
            $h -= 24;
        }

        $date->setTime($h, $m);
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * 🔹 シフト提出禁止日を算出（前半・後半対応）
     */
/**
 * 🔹 シフト提出禁止日を算出（前半・後半対応 完全版）
 */
protected function getShiftLockInfo(User $user, int $year, int $month): array
{
    $store = $user->store;
    if (!$store) return [false, []];

    $today      = now();
    $targetDate = Carbon::create($year, $month, 1);
    $monthEnd   = $targetDate->copy()->endOfMonth();

    // ====================================
    // 🔸 0. 過去の月 → 全日ロック
    // ====================================
    if ($targetDate->lt($today->copy()->startOfMonth())) {
        return [true, range(1, $monthEnd->day)];
    }

    // ====================================
    // 🔸 1. 未来の月 → 全日アンロック
    // ====================================
    if ($targetDate->gt($today->copy()->startOfMonth())) {
        return [false, []];
    }

    // ====================================
    // 🔸 2. 今月のみ締切を判定
    // ====================================
    // --- single モード ---
    if ($store->shift_deadline_type !== 'split') {
        $deadline = Carbon::create($year, $month, $store->shift_deadline_day ?? 10);

        if ($today->gt($deadline)) {
            return [true, range(1, $monthEnd->day)];
        }
        return [false, []];
    }

    // --- split モード（前半 / 後半） ---
    $firstLimit  = Carbon::create($year, $month, $store->shift_first_half_deadline  ?? 10);
    $secondLimit = Carbon::create($year, $month, $store->shift_second_half_deadline ?? 25);

    // ====================================
    // 🔸 3. 前半締切前 → 後半はロック（1〜15だけ入力可能）
    // ====================================
    if ($today->lte($firstLimit)) {
        return [
            false,
            range(16, $monthEnd->day),
        ];
    }

    // ====================================
    // 🔸 4. 前半締切後〜後半締切前 → 前半をロック（16〜末だけ入力可能）
    // ====================================
    if ($today->gt($firstLimit) && $today->lte($secondLimit)) {
        return [
            false,
            range(1, 15),
        ];
    }

    // ====================================
    // 🔸 5. 後半締切後 → 全ロック
    // ====================================
    if ($today->gt($secondLimit)) {
        return [
            true,
            range(1, $monthEnd->day),
        ];
    }

    return [false, []];
}




    /**
     * 🔹 1日分保存（※今は JS 側は一括提出のみ使用想定なら未使用でもOK）
     */
    public function save(Request $request)
    {
        try {
            $date    = $request->shift_date;
            $userId  = $request->user_id;
            $isDayOff = $request->boolean('is_day_off');

            $user = User::findOrFail($userId);
            $baseDate = Carbon::parse($date)->format('Y-m-d');
            $year  = (int)Carbon::parse($date)->year;
            $month = (int)Carbon::parse($date)->month;

            // 🔹 ロック判定
            [, $lockedDates] = $this->getShiftLockInfo($user, $year, $month);
            $day = (int)Carbon::parse($date)->day;
            if (in_array($day, $lockedDates, true)) {
                return response()->json(['success' => false, 'message' => '⛔ この日の提出期限は過ぎています。'], 403);
            }

            if (Shift::where('user_id', $userId)->where('shift_date', $baseDate)->exists()) {
                return response()->json(['success' => false, 'message' => '⚠ この日はすでに確定済みです'], 409);
            }

            $startDateTime = $isDayOff ? null : $this->normalizeShiftDateTime($baseDate, $request->start_time);
            $endDateTime   = $isDayOff ? null : $this->normalizeShiftDateTime($baseDate, $request->end_time);

            if (!$isDayOff && (!$startDateTime || !$endDateTime)) {
                return response()->json(['success' => false, 'message' => '時刻の形式が正しくありません。'], 422);
            }

            ShiftRequest::updateOrCreate(
                ['user_id' => $userId, 'shift_date' => $baseDate],
                [
                    'is_day_off' => $isDayOff,
                    'start_time' => $startDateTime,
                    'end_time'   => $endDateTime,
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
            $shifts = $request->input('shifts', []);

            $user = User::findOrFail($userId);

            if (empty($shifts)) {
                return response()->json(['success' => false, 'message' => 'シフトが送信されていません。'], 422);
            }

            // どの月かを1件目から推定
            $firstKey = array_key_first($shifts);
            $baseDate = Carbon::parse($firstKey);
            [, $lockedDates] = $this->getShiftLockInfo($user, (int)$baseDate->year, (int)$baseDate->month);

            foreach ($shifts as $date => $s) {
                $baseDate = Carbon::parse($date)->format('Y-m-d');
                $day      = (int)Carbon::parse($date)->day;

                $isDayOff   = !empty($s['is_day_off']);
                $startInput = $s['start_time'] ?? null;
                $endInput   = $s['end_time'] ?? null;

                // 🔹 締切超過の日はスキップ
                if (in_array($day, $lockedDates, true)) {
                    continue;
                }

                // すでに確定済みならスキップ
                if (Shift::where('user_id', $userId)->where('shift_date', $baseDate)->exists()) {
                    continue;
                }

                $startDateTime = $isDayOff ? null : $this->normalizeShiftDateTime($baseDate, $startInput);
                $endDateTime   = $isDayOff ? null : $this->normalizeShiftDateTime($baseDate, $endInput);

                if (!$isDayOff && (!$startDateTime || !$endDateTime)) {
                    // その日だけ飛ばして続行
                    continue;
                }

                ShiftRequest::updateOrCreate(
                    ['user_id' => $userId, 'shift_date' => $baseDate],
                    [
                        'is_day_off' => $isDayOff,
                        'start_time' => $startDateTime,
                        'end_time'   => $endDateTime,
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
                $q->select('id')
                  ->from('users')
                  ->where('company_id', $company->id)
                  ->whereNull('deleted_at');
            })
            ->orderBy('shift_date')
            ->get()
            ->groupBy('user_id');

        return view('company.shifts.requests', compact('company', 'requests'));
    }

    /**
     * 🔹 月単位の提出期限チェック（必要なら他の場所で利用）
     */
    protected function isShiftDeadlinePassed(User $user, int $year, int $month): bool
    {
        [$deadlinePassed, ] = $this->getShiftLockInfo($user, $year, $month);
        return $deadlinePassed;
    }
}
