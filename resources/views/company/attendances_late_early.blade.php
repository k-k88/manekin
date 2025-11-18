@extends('layouts.app')

@section('content')
<div class="container">
    <h2>{{ $company->name }} - 遅刻・早退一覧</h2>

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
                <th>遅刻</th>
                <th>早退</th>
            </tr>
        </thead>
        <tbody>
            @foreach($attendances as $attendance)
            <tr>
                <td>{{ $attendance->date->format('Y-m-d') }}</td>
                <td>{{ $attendance->user->name }}</td>
                <td>{{ $attendance->clock_in ? $attendance->clock_in->format('H:i') : '' }}</td>
                <td>{{ $attendance->clock_out ? $attendance->clock_out->format('H:i') : '' }}</td>
                <td>
                    @if($attendance->late_minutes > 0)
                        ○ ({{ intdiv($attendance->late_minutes, 60) }}h{{ $attendance->late_minutes % 60 }}m)
                    @endif
                </td>
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
