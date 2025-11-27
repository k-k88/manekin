{{-- resources/views/company/today_attendances.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-4">🕓 本日の出勤者一覧</h2>

    @php
        // 出勤者(勤怠のある人)をフィルタ
        $workingList = collect($list)->filter(fn($row) => $row['attendance']);
    @endphp

    @if($workingList->isEmpty())
        <div class="alert alert-info">本日出勤している社員はいません。</div>
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
                        @foreach ($workingList as $row)
                            @php
                                $user  = $row['user'];
                                $att   = $row['attendance'];
                                $shift = $row['shift'];
                            @endphp

                            <tr>
                                {{-- 社員名 --}}
                                <td>{{ $user->name }}</td>

                                {{-- 出勤時刻 --}}
                                <td>
                                    {{ $att->clock_in ? \Carbon\Carbon::parse($att->clock_in)->format('H:i') : '—' }}
                                </td>

                                {{-- 退勤時刻 --}}
                                <td>
                                    {{ $att->clock_out ? \Carbon\Carbon::parse($att->clock_out)->format('H:i') : '—' }}
                                </td>

                                {{-- 休憩開始 --}}
                                <td>
                                    {{ $att->break_start ? \Carbon\Carbon::parse($att->break_start)->format('H:i') : '—' }}
                                </td>

                                {{-- 休憩終了 --}}
                                <td>
                                    {{ $att->break_end ? \Carbon\Carbon::parse($att->break_end)->format('H:i') : '—' }}
                                </td>

                                {{-- 状態 --}}
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

                                {{-- 店舗 --}}
                                <td>
                                    @if($att->store)
                                        {{ $att->store->name }}
                                    @elseif($shift && $shift->store)
                                        {{ $shift->store->name }}
                                    @else
                                        —
                                    @endif
                                </td>

                            </tr>
                        @endforeach
                    </tbody>

                </table>
            </div>
        </div>
    @endif
</div>
@endsection
