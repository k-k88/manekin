{{-- resources/views/company/today_attendances.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-4">🕓 本日の出勤者一覧</h2>

    @php
        // 出勤者(勤怠のある人)
        $workingList = collect($list)->filter(fn($row) => $row['attendance']);

        // 出勤予定者(勤怠なしでシフトあり)
        $scheduledList = collect($list)->filter(fn($row) => !$row['attendance'] && $row['shift'] && !$row['shift']->is_day_off && !$row['shift']->is_paid_leave);

        // 休暇者(有休・希望休・シフト未登録)
        $leaveList = collect($list)->filter(fn($row) => !$row['attendance'] && (!$row['shift'] || $row['shift']->is_day_off || $row['shift']->is_paid_leave));
    @endphp

    {{-- 出勤者 --}}
    @if($workingList->isEmpty())
        <div class="alert alert-info">本日出勤している社員はいません。</div>
    @else
        <div class="card shadow-sm mb-4">
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
                            <th>支店</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($workingList as $row)
                            @php
                                $user  = $row['user'];
                                $att   = $row['attendance'];
                                $shift = $row['shift'];
                            @endphp
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $att->clock_in ? \Carbon\Carbon::parse($att->clock_in)->format('H:i') : '—' }}</td>
                                <td>{{ $att->clock_out ? \Carbon\Carbon::parse($att->clock_out)->format('H:i') : '—' }}</td>
                                <td>{{ $att->break_start ? \Carbon\Carbon::parse($att->break_start)->format('H:i') : '—' }}</td>
                                <td>{{ $att->break_end ? \Carbon\Carbon::parse($att->break_end)->format('H:i') : '—' }}</td>
                                <td>
                                    @if(!$att->clock_out)
                                        @if($att->break_start && !$att->break_end)
                                            <span class="badge bg-warning text-dark">休憩中</span>
                                        @else
                                            <span class="badge bg-success">出勤中</span>
                                        @endif
                                    @else
                                        <span class="badge bg-secondary">退勤済み</span>
                                    @endif
                                </td>
                                <td>{{ $att->store?->name ?? ($shift?->store?->name ?? $user->store?->name ?? '—') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- 出勤予定者 --}}
    @if($scheduledList->isNotEmpty())
        <h2 class="mb-3">📌 本日の出勤予定者</h2>
        <div class="card shadow-sm mb-4">
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>社員名</th>
                            <th>出勤予定</th>
                            <th>退勤予定</th>
                            <th>支店</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($scheduledList as $row)
                            @php
                                $user = $row['user'];
                                $shift = $row['shift'];
                            @endphp
                            <tr class="table-danger">
                                <td>{{ $user->name }}</td>
                                <td>{{ $shift->start_time ? \Carbon\Carbon::parse($shift->start_time)->format('H:i') : '—' }}</td>
                                <td>{{ $shift->end_time ? \Carbon\Carbon::parse($shift->end_time)->format('H:i') : '—' }}</td>
                                <td>{{ $shift->store?->name ?? $user->store?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- 休暇者 --}}
    @if($leaveList->isNotEmpty())
        <h2 class="mb-3">🚘 本日の休暇者</h2>
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>社員名</th>
                            <th>休暇種別</th>
                            <th>支店</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($leaveList as $row)
                            @php
                                $user = $row['user'];
                                $shift = $row['shift'];
                                $type = $shift
                                    ? ($shift->is_paid_leave ? '有休' : ($shift->is_day_off ? '希望休' : '休暇'))
                                    : '休暇';
                                // 支店名は勤怠・シフト・ユーザーの順で取得
                                $storeName = $row['attendance']?->store?->name
                                             ?? $shift?->store?->name
                                             ?? $user->store?->name
                                             ?? '—';
                            @endphp
                            <tr class="{{ $shift ? ($shift->is_paid_leave ? 'table-primary' : 'table-info') : 'table-secondary' }}">
                                <td>{{ $user->name }}</td>
                                <td>{{ $type }}</td>
                                <td>{{ $storeName }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
@endsection
