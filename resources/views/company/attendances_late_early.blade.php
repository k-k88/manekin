@extends('layouts.app')

@section('content')
<div class="container">
    <h2>{{ $company->name }} - 遅刻・早退一覧</h2>

    <div class="d-flex gap-2">
        <a href="{{ route('company.dashboard', $company->id) }}" class="btn btn-outline-secondary">
            ← ダッシュボード
        </a>
    </div>

    <form method="GET" class="mb-3">
        <input type="month" name="month" value="{{ $month }}">
        <button type="submit" class="btn btn-primary">絞り込み</button>
    </form>

    <a href="{{ route('company.dashboard', $company->id) }}" class="btn btn-outline-secondary">← ダッシュボード</a>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>日付</th>
                <th>社員名</th>
                <th>出勤</th>
                <th>退勤</th>

                {{-- ★ シフト時間を追加 --}}
                <th>シフト開始</th>
                <th>シフト終了</th>

                <th>遅刻</th>
                <th>早退</th>
            </tr>
        </thead>
        <tbody>
            @foreach($attendances as $attendance)
            @php
                // Controllerでwith('shiftOfDay')してるので null安全にできる
                $shift = $attendance->shiftOfDay ?? null;
            @endphp

            <tr>
                <td>{{ $attendance->date->format('Y-m-d') }}</td>
                <td>{{ $attendance->user->name }}</td>

                {{-- 出勤 --}}
                <td>
                    {{ $attendance->clock_in
                        ? \Carbon\Carbon::parse($attendance->clock_in)->format('H:i')
                        : '' }}
                </td>

                {{-- 退勤 --}}
                <td>
                    {{ $attendance->clock_out
                        ? \Carbon\Carbon::parse($attendance->clock_out)->format('H:i')
                        : '' }}
                </td>

                {{-- ★ シフト開始 --}}
                <td>
                    @if ($shift && !$shift->is_day_off)
                        {{ \Carbon\Carbon::parse($shift->start_time)->format('H:i') }}
                    @else
                        -
                    @endif
                </td>

                {{-- ★ シフト終了（29:00対応） --}}
                <td>
                    @if ($shift && !$shift->is_day_off)
                        {{ \Carbon\Carbon::parse($shift->end_time)->format('H:i') }}
                    @else
                        -
                    @endif
                </td>

               {{-- 遅刻 --}}
{{-- 遅刻 --}}
<td>
    @php $late = $attendance->late_minutes; @endphp
    @if($late > 0)
        ○ ({{ intdiv($late, 60) }}h{{ $late % 60 }}m)
    @endif
</td>

                {{-- 早退 --}}
                <td>
                    @if($attendance->early_leave_minutes > 0)
                        ○ ({{ intdiv($attendance->early_leave_minutes, 60) }}h{{ $attendance->early_leave_minutes % 60 }}m)
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{ $attendances->links() }}
</div>
@endsection
