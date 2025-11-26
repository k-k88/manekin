@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <h2 class="mb-2">🕒 {{ $company->name }} 勤怠一覧</h2>
        <div class="d-flex gap-2">
            <a href="{{ route('company.dashboard', $company->id) }}" class="btn btn-outline-secondary">
                ← ダッシュボード
            </a>
            <a href="{{ route('company.attendances.create', $company->id) }}" class="btn btn-success">
                ＋ 勤怠を追加
            </a>
        </div>
    </div>

    {{-- メッセージ --}}
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>⚠️ {{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- フィルター --}}
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-auto">
                    <input type="month" name="month"
                           value="{{ request('month', now()->format('Y-m')) }}"
                           class="form-control">
                </div>
                <div class="col-md-auto">
                    <select name="user_id" class="form-select">
                        <option value="">全社員</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> 表示
                    </button>
                    <a href="{{ route('company.attendances', $company->id) }}" class="btn btn-outline-secondary">
                        リセット
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- 勤怠一覧 --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 70vh;">
                <table class="table table-hover table-bordered align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>社員名</th>
                            <th>日付</th>
                            <th>出勤</th>
                            <th>退勤</th>
                            <th>休憩開始</th>
                            <th>休憩終了</th>
                            <th>勤務時間</th>
                            <th>ステータス</th>
                            <th class="text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attendances as $attendance)
                            @php
                                $formatTime = fn($t) => $t ? \Carbon\Carbon::parse($t)->format('H:i') : '';
                            @endphp
                            <tr>
                                <td class="text-nowrap">{{ $attendance->user->name }}</td>
                                <td>{{ optional($attendance->date)->format('Y-m-d') }}</td>

                                <td>
                                    <form action="{{ route('company.attendances.update', ['company' => $company->id, 'attendance' => $attendance->id]) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="clock_in" class="form-control form-control-sm time-input" value="{{ $formatTime($attendance->clock_in) }}">
                                </td>

                                <td>
                                        <input type="text" name="clock_out" class="form-control form-control-sm time-input" value="{{ $formatTime($attendance->clock_out) }}">
                                </td>

                                <td>
                                        <input type="text" name="break_start" class="form-control form-control-sm time-input" value="{{ $formatTime($attendance->break_start) }}">
                                </td>

                                <td>
                                        <input type="text" name="break_end" class="form-control form-control-sm time-input" value="{{ $formatTime($attendance->break_end) }}">
                                </td>

                                {{-- 勤務時間 --}}
                                <td>
                                    @if ($attendance->clock_in && $attendance->clock_out)
                                        @php
                                            $hours = floor($attendance->worked_hours);
                                            $minutes = round(($attendance->worked_hours - $hours) * 60);
                                        @endphp
                                        <span class="fw-bold">{{ $hours }}時間{{ $minutes }}分</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>

                                <td>
                                    @if ($attendance->status === 'approved')
                                        <span class="badge bg-success">承認済</span>
                                    @elseif ($attendance->status === 'pending')
                                        <span class="badge bg-warning text-dark">申請中</span>
                                    @else
                                        <span class="badge bg-secondary">未申請</span>
                                    @endif
                                </td>

                                {{-- 操作ボタン（サイズ揃え） --}}
                                <td class="text-center">
                                    <div class="d-flex gap-2 justify-content-center flex-wrap" style="max-width: 160px; margin: auto;">
                                        {{-- 更新 --}}
                                        <form action="{{ route('company.attendances.update', ['company' => $company->id, 'attendance' => $attendance->id]) }}" method="POST" class="flex-fill">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="btn btn-primary btn-sm w-100">更新</button>
                                        </form>

                                        {{-- 削除 --}}
                                        <form action="{{ route('company.attendances.destroy', ['company' => $company->id, 'attendance' => $attendance->id]) }}" method="POST" onsubmit="return confirm('この勤怠データを削除しますか？');" class="flex-fill">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">削除</button>
                                        </form>
                                    </div>
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">勤怠データがありません。</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- 時間入力補正 --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.time-input').forEach(input => {
        input.addEventListener('input', function(e) {
            let val = e.target.value.replace(/[^0-9]/g, '');
            if (val.length > 4) val = val.substring(0, 4);

            if (val.length >= 3) {
                let h = val.substring(0, 2);
                let m = val.substring(2, 4);
                if (m && parseInt(m) > 59) m = '59';
                val = h + ':' + (m ?? '');
            }

            e.target.value = val;
        });
    });
});
</script>
@endsection
