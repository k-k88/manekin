{{-- resources/views/company/today_attendances.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-4">🕓 本日の出勤者一覧</h2>

    @if($attendances->isEmpty())
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
                            <th>退勤 / 状態</th>
                            <th>休憩開始</th>
                            <th>休憩終了</th>
                            <th>店舗</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($attendances as $attendance)
                            <tr>
                                {{-- 社員名 --}}
                                <td>{{ $attendance->user->name }}</td>

                                {{-- 出勤時間 --}}
                                <td>
                                    {{ $attendance->clock_in ? \Carbon\Carbon::parse($attendance->clock_in)->format('H:i') : '—' }}
                                </td>

                                {{-- 退勤 / ステータス表示 --}}
                                <td>
                                    @if(is_null($attendance->clock_out))
                                        {{-- 退勤していない ※ステータス判定 --}}
                                        @if($attendance->break_start && is_null($attendance->break_end))
                                            {{-- 休憩中 --}}
                                            <span class="badge bg-warning text-dark">休憩中</span>
                                        @else
                                            {{-- 出勤中 --}}
                                            <span class="badge bg-success">出勤中</span>
                                        @endif
                                    @else
                                        {{-- 退勤済み --}}
                                        {{ \Carbon\Carbon::parse($attendance->clock_out)->format('H:i') }}
                                    @endif
                                </td>

                                {{-- 休憩開始 --}}
                                <td>
                                    {{ $attendance->break_start ? \Carbon\Carbon::parse($attendance->break_start)->format('H:i') : '—' }}
                                </td>

                                {{-- 休憩終了 --}}
                                <td>
                                    {{ $attendance->break_end ? \Carbon\Carbon::parse($attendance->break_end)->format('H:i') : '—' }}
                                </td>

                                {{-- 店舗 --}}
                                <td>{{ optional($attendance->store)->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
