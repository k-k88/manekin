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
            <input type="month" id="month" name="month" class="form-control"
                   value="{{ request('month', now()->format('Y-m')) }}">
        </div>
        <div>
            <label for="user_id">社員</label>
            <select id="user_id" name="user_id" class="form-select">
                <option value="">全員</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="align-self-end">
            <button type="submit" class="btn btn-primary">表示</button>
            <a href="{{ route('company.payrolls', ['company' => $company->id]) }}"
               class="btn btn-outline-secondary">リセット</a>
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
                    <th>有給</th>
                    <th>時給</th>
                    <th>給与</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($attendances as $attendance)
                    <tr @if($attendance->is_paid_leave_for_view) style="background-color:#cfe2ff;" @endif>
                        <td>{{ $attendance->user->name }}</td>

                        {{-- 日付 --}}
                        <td>{{ \Carbon\Carbon::parse($attendance->date)->format('Y-m-d') }}</td>

                        {{-- 出勤 --}}
                        <td>{{ $attendance->clock_in_for_view ?? '-' }}</td>

                        {{-- 退勤 --}}
                        <td>{{ $attendance->clock_out_for_view ?? '-' }}</td>

                        {{-- 勤務・休憩時間 --}}
                        <td>{{ number_format($attendance->hours, 2) }} h</td>
                        <td>{{ number_format(($attendance->break_minutes ?? 0) / 60, 2) }} h</td>

                        {{-- 有給 --}}
                        <td>{{ $attendance->is_paid_leave_for_view ? '有休' : '-' }}</td>

                        {{-- 時給・支給額 --}}
                        <td>{{ number_format($attendance->effective_wage) }} 円</td>
                        <td>{{ number_format($attendance->pay) }} 円</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted">勤怠データがありません</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- 総給与 --}}
    @if($attendances->count())
        <div class="mt-3 text-end fw-bold">
            総給与: {{ number_format($attendances->sum('pay')) }} 円
        </div>
    @endif
</div>
@endsection
