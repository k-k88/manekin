@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-4">🕒 {{ $company->name }} 勤怠一覧</h2>

    {{-- 🔹 ナビボタン --}}
    <div class="mb-3 d-flex gap-2">
        <a href="{{ route('company.dashboard', $company->id) }}" class="btn btn-secondary">← ダッシュボードに戻る</a>
        <a href="{{ route('company.attendances.create', $company->id) }}" class="btn btn-success">＋勤怠を追加</a>
    </div>

    {{-- 🔹 メッセージ表示 --}}
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
        </div>
    @endif

    {{-- 🔹 月・社員フィルター --}}
    <form method="GET" class="mb-3 d-flex gap-2 align-items-center flex-wrap">
        <input type="month" name="month" value="{{ request('month', now()->format('Y-m')) }}" class="form-control w-auto">
        <select name="user_id" class="form-select w-auto">
            <option value="">全社員</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                    {{ $user->name }}
                </option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary">表示</button>
        <a href="{{ route('company.attendances', $company->id) }}" class="btn btn-outline-secondary">リセット</a>
    </form>

    {{-- 🔹 勤怠一覧テーブル --}}
    <table class="table table-bordered table-hover align-middle shadow-sm">
        <thead class="table-light">
            <tr>
                <th>社員名</th>
                <th>日付</th>
                <th>出勤時刻</th>
                <th>退勤時刻</th>
                <th>勤務時間</th>
                <th>ステータス</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($attendances as $attendance)
                @php
                    $clockIn = $attendance->clock_in ? \Carbon\Carbon::parse($attendance->clock_in) : null;
                    $clockOut = $attendance->clock_out ? \Carbon\Carbon::parse($attendance->clock_out) : null;

                    $displayClockIn = $clockIn ? $clockIn->format('H:i') : '';
                    $displayClockOut = '';

                    if ($attendance->clock_out) {
                        $outHour = (int)substr($attendance->clock_out, 0, 2);
                        $outMin = (int)substr($attendance->clock_out, 3, 2);
                        $displayClockOut = $outHour >= 24
                            ? sprintf('%02d:%02d', $outHour - 24, $outMin)
                            : sprintf('%02d:%02d', $outHour, $outMin);
                    }
                @endphp

                <tr>
                    <td>{{ $attendance->user->name }}</td>
                    <td>{{ $attendance->date }}</td>

                    {{-- 🔸 編集フォーム --}}
                    <form action="{{ route('company.attendances.update', ['company' => $company->id, 'attendance' => $attendance->id]) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <td>
                            <input type="text" name="clock_in" class="form-control time-input" value="{{ $displayClockIn }}" placeholder="HH:MM">
                        </td>
                        <td>
                            <input type="text" name="clock_out" class="form-control time-input" value="{{ $displayClockOut }}" placeholder="HH:MM">
                        </td>

                        {{-- 🔸 勤務時間計算 --}}
                        <td>
                            @if ($clockIn && $attendance->clock_out)
                                @php
                                    $outHourInt = (int)substr($attendance->clock_out, 0, 2);
                                    $outMinuteInt = (int)substr($attendance->clock_out, 3, 2);
                                    $calcOut = $clockIn->copy();

                                    if ($outHourInt >= 24) {
                                        $calcOut->addDay()->setTime($outHourInt - 24, $outMinuteInt);
                                    } else {
                                        $calcOut->setTime($outHourInt, $outMinuteInt);
                                        if ($calcOut->lessThanOrEqualTo($clockIn)) {
                                            $calcOut->addDay();
                                        }
                                    }

                                    $hours = $clockIn->diffInMinutes($calcOut) / 60;
                                @endphp
                                {{ number_format($hours, 2) }} 時間
                            @else
                                -
                            @endif
                        </td>

                        {{-- 🔸 ステータス --}}
                        <td>
                            @if ($attendance->status === 'approved')
                                <span class="badge bg-success">承認済み</span>
                            @elseif ($attendance->status === 'pending')
                                <span class="badge bg-warning text-dark">申請中</span>
                            @else
                                <span class="badge bg-secondary">未申請</span>
                            @endif
                        </td>

                        {{-- 🔸 操作ボタン --}}
                        <td class="d-flex gap-1">
                            <button type="submit" class="btn btn-sm btn-primary">更新</button>
                    </form>

                            <form action="{{ route('company.attendances.destroy', ['company' => $company->id, 'attendance' => $attendance->id]) }}" 
                                  method="POST" 
                                  onsubmit="return confirm('この勤怠データを削除しますか？');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">削除</button>
                            </form>
                        </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted">勤怠データがありません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- 🔹 コロン自動挿入スクリプト --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.time-input').forEach(input => {
        input.addEventListener('input', function(e) {
            let val = e.target.value.replace(/\D/g, '');
            if (val.length >= 3) {
                val = val.substring(0, 2) + ':' + val.substring(2, 4);
            }
            e.target.value = val;
        });
    });
});
</script>
@endsection
