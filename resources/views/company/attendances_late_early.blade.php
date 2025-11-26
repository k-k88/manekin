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

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>日付</th>
                <th>社員名</th>
                <th>出勤</th>
                <th>退勤</th>
                <th>シフト開始</th>
                <th>シフト終了</th>
                <th>遅刻</th>
                <th>早退</th>
            </tr>
        </thead>
        <tbody>
            @foreach($attendances as $attendance)
            @php
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

                {{-- シフト開始 --}}
                <td>
                    @if ($shift && !$shift->is_day_off)
                        {{ \Carbon\Carbon::parse($shift->start_time)->format('H:i') }}
                    @else
                        -
                    @endif
                </td>

                {{-- シフト終了 --}}
                <td>
                    @if ($shift && !$shift->is_day_off)
                        {{ \Carbon\Carbon::parse($shift->end_time)->format('H:i') }}
                    @else
                        -
                    @endif
                </td>

                {{-- 遅刻 --}}
                <td>
                    @php 
                        $late = $attendance->late_minutes; 
                        $late_hours = intdiv($late, 60);
                        $late_mins = $late % 60;
                    @endphp
                    @if($late > 0)
                        ○ (
                        @if($late_hours > 0) {{ $late_hours }}時間 @endif
                        @if($late_mins > 0) {{ $late_mins }}分 @endif
                        )
                    @endif
                </td>

                {{-- 早退 --}}
                <td>
                    @php 
                        $early = $attendance->early_leave_minutes; 
                        $early_hours = intdiv($early, 60);
                        $early_mins = $early % 60;
                    @endphp
                    @if($early > 0)
                        ○ (
                        @if($early_hours > 0) {{ $early_hours }}時間 @endif
                        @if($early_mins > 0) {{ $early_mins }}分 @endif
                        )
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{ $attendances->links() }}
</div>
@endsection
