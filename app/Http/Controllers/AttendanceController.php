<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Shift;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    /**
     * 勤怠一覧（月・社員フィルタ対応）
     */
    public function index(Request $request, Company $company)
    {
        $user = auth()->user();
        if ($user->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }

        $month  = $request->input('month', now()->format('Y-m'));
        $userId = $request->input('user_id');

        $startOfMonth = Carbon::parse($month)->startOfMonth();
        $endOfMonth   = Carbon::parse($month)->endOfMonth();

        $query = Attendance::where('company_id', $company->id)
            ->whereHas('user', fn($q) => $q->whereNull('deleted_at'))
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->with('user')
            ->orderByDesc('date');

        if (!empty($userId)) {
            $query->where('user_id', $userId);
        }

        $attendances = $query->paginate(20)->appends($request->query());

        $users = User::where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->get();

        return view('company.attendances', compact('company', 'attendances', 'users', 'month'));
    }

    /**
     * 勤怠追加画面
     */
    public function createAttendance(Company $company)
    {
        $user = auth()->user();
        if ($user->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }

        $users = User::where('company_id', $company->id)->get();
        return view('company.attendances_create', compact('company', 'users'));
    }

    /**
     * 遅刻・早退一覧
     */
 public function lateEarlyList(Request $request, Company $company)
{
    $user = auth()->user();
    if ($user->company_id !== $company->id) {
        abort(403, 'アクセス権がありません');
    }

    $month  = $request->input('month', now()->format('Y-m'));
    $startOfMonth = Carbon::parse($month)->startOfMonth();
    $endOfMonth   = Carbon::parse($month)->endOfMonth();

    // ★ shiftOfDay は preload（with）しない！
    $attendances = Attendance::with('user')
        ->where('company_id', $company->id)
        ->whereBetween('date', [$startOfMonth, $endOfMonth])
        ->where(function ($query) {
            $query->where('late_flag', 1)
                  ->orWhere('early_leave_flag', 1);
        })
        ->orderByDesc('date')
        ->paginate(20)
        ->appends($request->query());

    // フィルタ用ユーザー一覧
    $users = User::where('company_id', $company->id)
        ->whereNull('deleted_at')
        ->get();

    return view('company.attendances_late_early',
        compact('company', 'attendances', 'users', 'month')
    );
}



    /**
     * 勤怠保存（日またぎ・休憩対応）
     */
   public function storeAttendance(Request $request, Company $company)
{
    $authUser = auth()->user();
    if ($authUser->company_id !== $company->id) {
        abort(403, 'アクセス権がありません');
    }

    $request->validate([
        'user_id'     => 'required|exists:users,id',
        'date'        => 'required|date',
        'clock_in'    => 'required',
        'clock_out'   => 'required',
        'break_start' => 'nullable',
        'break_end'   => 'nullable',
    ]);

    try {
        // ① まず基本情報の確定
        $user = User::findOrFail($request->user_id);
        $date = Carbon::parse($request->date);

        // ② 時刻正規化
        $normalizeTime = function ($input) {
            $input = trim(str_replace(['：', '.', ' '], [':', ':', ''], $input));
            if (preg_match('/^\d{1,2}$/', $input)) return str_pad($input, 2, '0', STR_PAD_LEFT) . ':00';
            if (preg_match('/^(\d{1,2})(\d{2})$/', $input, $m)) return str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
            if (preg_match('/^(\d{1,2}):(\d{1,2})$/', $input, $m)) return str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . str_pad($m[2], 2, '0', STR_PAD_RIGHT);
            if (preg_match('/^\d{2}:\d{2}$/', $input)) return $input;
            return null;
        };

        $clockInStr  = $normalizeTime($request->clock_in);
        $clockOutStr = $normalizeTime($request->clock_out);

        if (!$clockInStr || !$clockOutStr) {
            throw new Exception('時刻の形式が正しくありません。');
        }

        // ③ Carbon 時間化
        [$hIn, $mIn]   = array_map('intval', explode(':', $clockInStr));
        [$hOut, $mOut] = array_map('intval', explode(':', $clockOutStr));

        foreach ([[$hIn, $mIn], [$hOut, $mOut]] as [$h, $m]) {
            if ($h < 0 || $h > 29 || $m < 0 || $m > 59) {
                throw new Exception('時刻は00:00〜29:59の範囲で入力してください。');
            }
        }

        $clockInTime  = $date->copy();
        if ($hIn >= 24) $clockInTime->addDay();
        $clockInTime->setTime($hIn % 24, $mIn);

        $clockOutTime = $date->copy();
        if ($hOut >= 24) $clockOutTime->addDay();
        $clockOutTime->setTime($hOut % 24, $mOut);

        if ($clockOutTime->lessThan($clockInTime)) $clockOutTime->addDay();

        // ④ ここで初めて遅刻・早退判定を呼ぶ
        [$lateFlag, $earlyLeaveFlag] =
            $this->computeLateEarlyFlags($user, $date, $clockInTime, $clockOutTime);

        // ⑤ 休憩計算
        $breakMinutes = 0;
        $breakStartTime = null;
        $breakEndTime = null;

        if ($request->break_start && $request->break_end) {
            $breakStartStr = $normalizeTime($request->break_start);
            $breakEndStr   = $normalizeTime($request->break_end);
            if (!$breakStartStr || !$breakEndStr) {
                throw new Exception('休憩時刻の形式が正しくありません。');
            }

            [$hBs, $mBs] = array_map('intval', explode(':', $breakStartStr));
            [$hBe, $mBe] = array_map('intval', explode(':', $breakEndStr));

            $breakStartTime = $date->copy();
            if ($hBs >= 24) $breakStartTime->addDay();
            $breakStartTime->setTime($hBs % 24, $mBs);

            $breakEndTime = $date->copy();
            if ($hBe >= 24) $breakEndTime->addDay();
            $breakEndTime->setTime($hBe % 24, $mBe);

            if ($breakEndTime->lessThan($breakStartTime)) $breakEndTime->addDay();

            $breakMinutes = $breakStartTime->diffInMinutes($breakEndTime);
        }

        // ⑥ 登録
        Attendance::create([
            'company_id'      => $company->id,
            'user_id'         => $user->id,
            'store_id'        => $user->store_id,
            'date'            => $date->format('Y-m-d'),
            'clock_in'        => $clockInTime->format('H:i:s'),
            'clock_out'       => $clockOutTime->format('H:i:s'),
            'break_start'     => $breakStartTime?->format('H:i:s'),
            'break_end'       => $breakEndTime?->format('H:i:s'),
            'break_minutes'   => $breakMinutes,
            'late_flag'       => $lateFlag,
            'early_leave_flag'=> $earlyLeaveFlag,
        ]);

        return redirect()->route('company.attendances', $company)
            ->with('success', '勤怠を追加しました。');

    } catch (Exception $e) {
        return back()->withInput()->withErrors(['clock_in' => $e->getMessage()]);
    }
}


    /**
     * 勤怠削除
     */
    public function destroyAttendance(Company $company, Attendance $attendance)
    {
        $authUser = auth()->user();
        if ($authUser->company_id !== $company->id || $attendance->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }

        try {
            $attendance->delete();
            return back()->with('success', '勤怠を削除しました。');
        } catch (\Exception $e) {
            return back()->with('error', '削除に失敗しました。');
        }
    }

    /**
     * 勤怠更新
     */
    public function updateAttendance(Request $request, Company $company, Attendance $attendance)
{
    $authUser = auth()->user();
    if ($authUser->company_id !== $company->id || $attendance->company_id !== $company->id) {
        abort(403, 'アクセス権がありません');
    }

    $request->validate([
        'clock_in'    => 'required',
        'clock_out'   => 'required',
        'break_start' => 'nullable',
        'break_end'   => 'nullable',
    ]);

    try {
        // ① この勤怠のユーザーを取得
        $user = User::findOrFail($attendance->user_id);

        // ② 日付の Carbon 変換
        $date = Carbon::parse($attendance->date);

        // ③ 時刻正規化
        $normalizeTime = function ($input) {
            $input = trim(str_replace(['：', '.', ' '], [':', ':', ''], $input));
            if (preg_match('/^\d{1,2}$/', $input)) return str_pad($input, 2, '0', STR_PAD_LEFT) . ':00';
            if (preg_match('/^(\d{1,2})(\d{2})$/', $input, $m)) return str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
            if (preg_match('/^(\d{1,2}):(\d{1,2})$/', $input, $m)) return str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . str_pad($m[2], 2, '0', STR_PAD_RIGHT);
            if (preg_match('/^\d{2}:\d{2}$/', $input)) return $input;
            return null;
        };

        $clockInStr  = $normalizeTime($request->clock_in);
        $clockOutStr = $normalizeTime($request->clock_out);

        if (!$clockInStr || !$clockOutStr) {
            throw new Exception('時刻の形式が正しくありません。');
        }

        // ④ Carbon 時間化
        [$hIn, $mIn]   = array_map('intval', explode(':', $clockInStr));
        [$hOut, $mOut] = array_map('intval', explode(':', $clockOutStr));

        foreach ([[$hIn, $mIn], [$hOut, $mOut]] as [$h, $m]) {
            if ($h < 0 || $h > 29 || $m < 0 || $m > 59) {
                throw new Exception('時刻は00:00〜29:59の範囲で入力してください。');
            }
        }

        $clockInTime  = $date->copy();
        if ($hIn >= 24) $clockInTime->addDay();
        $clockInTime->setTime($hIn % 24, $mIn);

        $clockOutTime = $date->copy();
        if ($hOut >= 24) $clockOutTime->addDay();
        $clockOutTime->setTime($hOut % 24, $mOut);

        if ($clockOutTime->lessThan($clockInTime)) $clockOutTime->addDay();

        // ⑤ ここで遅刻・早退判定を実行
        [$lateFlag, $earlyLeaveFlag] =
            $this->computeLateEarlyFlags($user, $date, $clockInTime, $clockOutTime);

        // ⑥ 休憩計算
        $breakMinutes = 0;
        $breakStartTime = null;
        $breakEndTime = null;

        if ($request->break_start && $request->break_end) {
            $breakStartStr = $normalizeTime($request->break_start);
            $breakEndStr   = $normalizeTime($request->break_end);
            if (!$breakStartStr || !$breakEndStr) {
                throw new Exception('休憩時刻の形式が正しくありません。');
            }

            [$hBs, $mBs] = array_map('intval', explode(':', $breakStartStr));
            [$hBe, $mBe] = array_map('intval', explode(':', $breakEndStr));

            $breakStartTime = $date->copy();
            if ($hBs >= 24) $breakStartTime->addDay();
            $breakStartTime->setTime($hBs % 24, $mBs);

            $breakEndTime = $date->copy();
            if ($hBe >= 24) $breakEndTime->addDay();
            $breakEndTime->setTime($hBe % 24, $mBe);

            if ($breakEndTime->lessThan($breakStartTime)) $breakEndTime->addDay();

            $breakMinutes = $breakStartTime->diffInMinutes($breakEndTime);
        }

        // ⑦ 更新
        $attendance->update([
            'clock_in'        => $clockInTime->format('H:i:s'),
            'clock_out'       => $clockOutTime->format('H:i:s'),
            'break_start'     => $breakStartTime?->format('H:i:s'),
            'break_end'       => $breakEndTime?->format('H:i:s'),
            'break_minutes'   => $breakMinutes,
            'late_flag'       => $lateFlag,
            'early_leave_flag'=> $earlyLeaveFlag,
        ]);

        return back()->with('success', '勤怠を更新しました。');

    } catch (Exception $e) {
        return back()->withInput()->withErrors(['clock_in' => $e->getMessage()]);
    }
}

    private function computeLateEarlyFlags(User $user, Carbon $date, Carbon $clockInTime, Carbon $clockOutTime)
{
    $shift = Shift::where('user_id', $user->id)
        ->where('shift_date', $date->format('Y-m-d'))
        ->where('status', 'approved')
        ->first();

    if (!$shift || $shift->is_day_off) {
        return [0, 0]; // 遅刻・早退なし
    }

    // シフト開始・終了
    $shiftStart = Carbon::parse($shift->shift_date . ' ' . $shift->start_time);
    $shiftEnd   = Carbon::parse($shift->shift_date . ' ' . $shift->end_time);

    // シフトが日またぎ（例）start 22:00 end 06:00
    if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
        $shiftEnd->addDay();
    }

    // 打刻も正常化（clock_in が翌日→比較合わせる）
    if ($clockInTime->lessThan($shiftStart)) {
        $clockInTime->addDay();
    }
    if ($clockOutTime->lessThan($shiftStart)) {
        $clockOutTime->addDay();
    }

    // ===== 判定 =====
    $late = $clockInTime->greaterThan($shiftStart->copy()->addMinutes(5)) ? 1 : 0;
    $early = $clockOutTime->lessThan($shiftEnd->copy()->subMinutes(5)) ? 1 : 0;

    return [$late, $early];
}
public function todayAttendances(Company $company)
{
    $today = \Carbon\Carbon::today()->format('Y-m-d');

    $attendances = \App\Models\Attendance::where('company_id', $company->id)
        ->whereDate('date', $today)
        ->with(['user', 'store'])
        ->orderBy('clock_in', 'asc')
        ->get();

    return view('company.today_attendances', compact('company', 'attendances'));
}



}
