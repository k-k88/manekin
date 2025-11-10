<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;
use Exception;

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

        // 🔹 月と社員フィルタを取得
        $month = $request->input('month', now()->format('Y-m'));
        $userId = $request->input('user_id');

        // 🔹 指定月の開始・終了日を取得
        $startOfMonth = Carbon::parse($month)->startOfMonth();
        $endOfMonth = Carbon::parse($month)->endOfMonth();

        // 🔹 勤怠クエリ（会社ID & 指定月）
        $query = Attendance::where('company_id', $company->id)
            ->whereHas('user', fn($q) => $q->whereNull('deleted_at'))
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->with('user')
            ->orderByDesc('date');

        // 🔹 社員指定があれば絞り込み
        if (!empty($userId)) {
            $query->where('user_id', $userId);
        }

        // 🔹 ページネーション対応
        $attendances = $query->paginate(20)->appends($request->query());

        // 🔹 全社員リスト
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
     * 勤怠保存（日またぎ対応・29:59までOK）
     */
    public function storeAttendance(Request $request, Company $company)
    {
        $authUser = auth()->user();
        if ($authUser->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'clock_in' => 'required',
            'clock_out' => 'required',
        ]);

        try {
            $user = User::findOrFail($request->user_id);
            $date = Carbon::parse($request->date);

            // 🔹 時刻整形関数
            $normalizeTime = function ($input) {
                $input = trim(str_replace(['：', '.', ' '], [':', ':', ''], $input));

                if (preg_match('/^\d{1,2}$/', $input)) return str_pad($input, 2, '0', STR_PAD_LEFT) . ':00';
                if (preg_match('/^(\d{1,2})(\d{2})$/', $input, $m)) return str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
                if (preg_match('/^(\d{1,2}):(\d{1,2})$/', $input, $m)) return str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . str_pad($m[2], 2, '0', STR_PAD_RIGHT);
                if (preg_match('/^\d{2}:\d{2}$/', $input)) return $input;

                return null;
            };

            $clockInStr = $normalizeTime($request->clock_in);
            $clockOutStr = $normalizeTime($request->clock_out);

            if (!$clockInStr || !$clockOutStr) {
                throw new Exception('時刻の形式が正しくありません（例: 6, 630, 18:05, 29:30）。');
            }

            [$hIn, $mIn] = array_map('intval', explode(':', $clockInStr));
            [$hOut, $mOut] = array_map('intval', explode(':', $clockOutStr));

            foreach ([[$hIn, $mIn], [$hOut, $mOut]] as [$h, $m]) {
                if ($h < 0 || $h > 29 || $m < 0 || $m > 59) {
                    throw new Exception('時刻は00:00〜29:59の範囲で入力してください。');
                }
            }

            // 🔹 日付計算
            $clockInDate = $date->copy();
            if ($hIn >= 24) $clockInDate->addDay();
            $clockInTime = Carbon::parse($clockInDate->format('Y-m-d') . ' ' . sprintf('%02d:%02d', $hIn % 24, $mIn));

            $clockOutDate = $date->copy();
            if ($hOut >= 24) $clockOutDate->addDay();
            $clockOutTime = Carbon::parse($clockOutDate->format('Y-m-d') . ' ' . sprintf('%02d:%02d', $hOut % 24, $mOut));

            if ($clockOutTime->lessThan($clockInTime)) {
                $clockOutTime->addDay();
            }

            $workedMinutes = $clockInTime->diffInMinutes($clockOutTime);
            $workedHours = round($workedMinutes / 60, 2);
            $hourlyWage = $user->hourly_wage ?? 0;
            $salary = $workedHours * $hourlyWage;

            Attendance::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'date' => $date->format('Y-m-d'),
                'clock_in' => $clockInTime,
                'clock_out' => $clockOutTime,
                'worked_hours' => $workedHours,
                'hourly_wage' => $hourlyWage,
                'salary' => $salary,
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
     * 勤怠更新（日またぎ対応・29:59までOK）
     */
    public function updateAttendance(Request $request, Company $company, Attendance $attendance)
    {
        $authUser = auth()->user();
        if ($authUser->company_id !== $company->id || $attendance->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }

        $request->validate([
            'clock_in' => 'required',
            'clock_out' => 'required',
        ]);

        try {
            $normalizeTime = function ($input) {
                $input = trim(str_replace(['：', '.', ' '], [':', ':', ''], $input));

                if (preg_match('/^\d{1,2}$/', $input)) return str_pad($input, 2, '0', STR_PAD_LEFT) . ':00';
                if (preg_match('/^(\d{1,2})(\d{2})$/', $input, $m)) return str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
                if (preg_match('/^(\d{1,2}):(\d{1,2})$/', $input, $m)) return str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . str_pad($m[2], 2, '0', STR_PAD_RIGHT);
                if (preg_match('/^\d{2}:\d{2}$/', $input)) return $input;

                return null;
            };

            $clockInStr = $normalizeTime($request->clock_in);
            $clockOutStr = $normalizeTime($request->clock_out);

            if (!$clockInStr || !$clockOutStr) {
                throw new \Exception('時刻の形式が正しくありません（例: 6, 630, 18:05, 29:30）。');
            }

            [$hIn, $mIn] = array_map('intval', explode(':', $clockInStr));
            [$hOut, $mOut] = array_map('intval', explode(':', $clockOutStr));

            foreach ([[$hIn, $mIn], [$hOut, $mOut]] as [$h, $m]) {
                if ($h < 0 || $h > 29 || $m < 0 || $m > 59) {
                    throw new \Exception('時刻は00:00〜29:59の範囲で入力してください。');
                }
            }

            $date = Carbon::parse($attendance->date);

            $clockInDate = $date->copy();
            if ($hIn >= 24) $clockInDate->addDay();
            $clockInTime = Carbon::parse($clockInDate->format('Y-m-d') . ' ' . sprintf('%02d:%02d', $hIn % 24, $mIn));

            $clockOutDate = $date->copy();
            if ($hOut >= 24) $clockOutDate->addDay();
            $clockOutTime = Carbon::parse($clockOutDate->format('Y-m-d') . ' ' . sprintf('%02d:%02d', $hOut % 24, $mOut));

            if ($clockOutTime->lessThan($clockInTime)) {
                $clockOutTime->addDay();
            }

            $workedMinutes = $clockInTime->diffInMinutes($clockOutTime);
            $workedHours = round($workedMinutes / 60, 2);
            $hourlyWage = $attendance->user->hourly_wage ?? 0;
            $salary = $workedHours * $hourlyWage;

            $attendance->update([
                'clock_in' => $clockInTime,
                'clock_out' => $clockOutTime,
                'worked_hours' => $workedHours,
                'hourly_wage' => $hourlyWage,
                'salary' => $salary,
            ]);

            return back()->with('success', '勤怠を更新しました。');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['clock_in' => $e->getMessage()]);
        }
    }
}
