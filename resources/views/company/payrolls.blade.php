@extends('layouts.app')
 
@section('content')
<div class="container mt-4">
    <h2 class="mb-4">💰 {{ $company->name }} 給与一覧</h2>

    <div class="mb-3">
        <a href="{{ route('company.dashboard', ['company' => $company->id]) }}" class="btn btn-secondary">
            ← ダッシュボードに戻る
        </a>
    </div>

    {{-- フィルター --}}
    <form method="GET" class="mb-3 d-flex gap-2 align-items-end flex-wrap">
        <div>
            <label for="month">月</label>
            <input type="month" id="month" name="month" class="form-control" value="{{ request('month', now()->format('Y-m')) }}">
        </div>
        <div>
            <label for="user_id">社員</label>
            <select id="user_id" name="user_id" class="form-select">
                <option value="">全員</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" @if(request('user_id') == $user->id) selected @endif>
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="align-self-end">
            <button type="submit" class="btn btn-primary">表示</button>
            <a href="{{ route('company.payrolls', ['company' => $company->id]) }}" class="btn btn-outline-secondary">リセット</a>
        </div>
    </form>

    {{-- CSV出力 --}}
    <a href="{{ route('company.payrollsCsv', ['company' => $company->id, 'month' => request('month')]) }}"
       class="btn btn-info mb-3">
       CSVでダウンロード
    </a>

    {{-- 給与テーブル --}}
    <div class="table-responsive">
    <table class="table table-bordered table-hover align-middle shadow-sm text-center">
        <thead class="table-light">
            <tr>
                <th>社員名</th>
                <th>日付</th>
                <th>出勤</th>
                <th>退勤</th>
                <th>勤務時間</th>
                <th>休憩時間</th>
                <th>時給</th>
                <th>給与</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($attendances as $attendance)
                @php
                    $clockIn  = $attendance->clock_in ? \Carbon\Carbon::parse($attendance->date . ' ' . $attendance->clock_in) : null;
                    $clockOut = $attendance->clock_out ? \Carbon\Carbon::parse($attendance->date . ' ' . $attendance->clock_out) : null;
                    $breakStart = $attendance->break_start ? \Carbon\Carbon::parse($attendance->date . ' ' . $attendance->break_start) : null;
                    $breakEnd   = $attendance->break_end   ? \Carbon\Carbon::parse($attendance->date . ' ' . $attendance->break_end) : null;

                    // 日跨ぎ対応
                    if($clockIn && $clockOut && $clockOut->lessThanOrEqualTo($clockIn)) {
                        $clockOut->addDay();
                    }
                    if($breakStart && $breakEnd && $breakEnd->lessThan($breakStart)) {
                        $breakEnd->addDay();
                    }

                    $displayIn  = $clockIn ? $clockIn->format('H:i') : '-';
                    $displayOut = $clockOut ? $clockOut->format('H:i') : '-';

                    $workMinutes = 0;
                    $breakMinutes = 0;

                    if($clockIn && $clockOut){
                        $workMinutes = $clockIn->diffInMinutes($clockOut);

                        if($breakStart && $breakEnd){
                            $breakMinutes = $breakStart->diffInMinutes($breakEnd);
                        }

                        $workMinutes -= $breakMinutes;
                        $workMinutes = max(0, $workMinutes);
                    }

                    $workHours = round($workMinutes / 60, 2);
                    $breakHours = round($breakMinutes / 60, 2);

                    $hourlyWage = $attendance->effective_wage ?? 0;
                    $pay = round($workHours * $hourlyWage);
                @endphp
                <tr>
                    <td>{{ $attendance->user->name }}</td>
                    <td>{{ $attendance->date }}</td>
                    <td>{{ $displayIn }}</td>
                    <td>{{ $displayOut }}</td>
                    <td>{{ number_format($workHours,2) }} h</td>
                    <td>{{ number_format($breakHours,2) }} h</td>
                    <td>{{ number_format($hourlyWage) }} 円</td>
                    <td>{{ number_format($pay) }} 円</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted">勤怠データがありません</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>

    {{-- 総給与 --}}
    @if($attendances->count())
        @php
            $totalPay = $attendances->sum(function($a){
                $clockIn  = $a->clock_in ? \Carbon\Carbon::parse($a->date . ' ' . $a->clock_in) : null;
                $clockOut = $a->clock_out ? \Carbon\Carbon::parse($a->date . ' ' . $a->clock_out) : null;
                $breakStart = $a->break_start ? \Carbon\Carbon::parse($a->date . ' ' . $a->break_start) : null;
                $breakEnd   = $a->break_end   ? \Carbon\Carbon::parse($a->date . ' ' . $a->break_end) : null;

                if($clockIn && $clockOut && $clockOut->lessThanOrEqualTo($clockIn)) $clockOut->addDay();
                if($breakStart && $breakEnd && $breakEnd->lessThan($breakStart)) $breakEnd->addDay();

                $workMinutes = $clockIn && $clockOut ? $clockIn->diffInMinutes($clockOut) : 0;
                $breakMinutes = $breakStart && $breakEnd ? $breakStart->diffInMinutes($breakEnd) : 0;

                $worked = max(0, $workMinutes - $breakMinutes);
                return round($worked/60 * ($a->effective_wage ?? 0));
            });
        @endphp
        <div class="mt-3 text-end fw-bold">
            総給与: {{ number_format($totalPay) }} 円
        </div>
    @endif
</div>
@endsection
