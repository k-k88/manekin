{{-- resources/views/company/today_attendances.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-4">🕓 本日の出勤者一覧</h2>

    @if($attendances->isEmpty() && $shiftUsers->isEmpty())
        <div class="alert alert-info">
            本日出勤している社員はいません。
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>社員名</th>
                            <th>出勤</th>
                            <th>退勤</th>
                            <th>休憩開始</th>
                            <th>休憩終了</th>
                            <th>状態</th>
                            <th>店舗</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- 勤怠データ --}}
                        @foreach ($attendances as $attendance)
                            <tr>
                                <td>{{ $attendance->user->name }}</td>
                                <td>{{ $attendance->clock_in ? \Carbon\Carbon::parse($attendance->clock_in)->format('H:i') : '—' }}</td>
                                <td>{{ $attendance->clock_out ? \Carbon\Carbon::parse($attendance->clock_out)->format('H:i') : '—' }}</td>
                                <td>{{ $attendance->break_start ? \Carbon\Carbon::parse($attendance->break_start)->format('H:i') : '—' }}</td>
                                <td>{{ $attendance->break_end ? \Carbon\Carbon::parse($attendance->break_end)->format('H:i') : '—' }}</td>
                                <td>
                                    @if(is_null($attendance->clock_out))
                                        @if($attendance->break_start && is_null($attendance->break_end))
                                            <span class="badge bg-warning text-dark">休憩中</span>
                                        @else
                                            <span class="badge bg-success">出勤中</span>
                                        @endif
                                    @else
                                        <span class="badge bg-secondary">退勤済み</span>
                                    @endif
                                </td>
                                <td>{{ optional($attendance->store)->name ?? '—' }}</td>
                            </tr>
                        @endforeach

                        {{-- 勤怠未作成のシフト予定 --}}
                        @foreach ($shiftUsers as $shift)
                            <tr class="table-info">
                                <td>{{ $shift->user->name }}</td>
                                <td>{{ \Carbon\Carbon::parse($shift->start_time)->format('H:i') }}</td>
                                <td>{{ \Carbon\Carbon::parse($shift->end_time)->format('H:i') }}</td>
                                <td>—</td>
                                <td>—</td>
                                <td><span class="badge bg-primary">出勤予定</span></td>
                                <td>{{ optional($shift->store)->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
