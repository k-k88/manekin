{{-- resources/views/company/payrolls.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-4">💰 {{ $company->name }} 日別給与一覧</h2>

    <div class="mb-3">
        <a href="{{ route('company.dashboard', ['company' => $company->id]) }}" class="btn btn-secondary">
            ← ダッシュボードに戻る
        </a>
    </div>

    {{-- フラッシュメッセージ --}}
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

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
                    <option value="{{ $user->id }}" @if(request('user_id') == $user->id) selected @endif>
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="align-self-end">
            <button type="submit" class="btn btn-primary">表示</button>
            <a href="{{ route('company.payrolls', ['company' => $company->id]) }}" 
               class="btn btn-outline-secondary">
               リセット
            </a>
        </div>
    </form>

    {{-- 勤怠から給与生成ボタン --}}
    <a href="{{ route('company.generatePayroll', ['company' => $company->id]) }}" 
       class="btn btn-success mb-3">
        勤怠から給与を生成する
    </a>

    {{-- CSV出力 --}}
    <a href="{{ route('company.payrollsCsv', ['company' => $company->id, 'month' => request('month')]) }}"
       class="btn btn-info mb-3">
        CSVでダウンロード
    </a>

    {{-- 給与テーブル --}}
    <table class="table table-bordered table-hover align-middle shadow-sm">
        <thead class="table-light">
            <tr>
                <th>社員名</th>
                <th>日付</th>
                <th>出勤時刻</th>
                <th>退勤時刻</th>
                <th>勤務時間</th>
                <th>時給</th>
                <th>給与</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($attendances as $attendance)
                @php
                    $hours = 0;
                    $pay = 0;
                    if ($attendance->clock_in && $attendance->clock_out) {
                        $hours = \Carbon\Carbon::parse($attendance->clock_in)
                            ->diffInMinutes($attendance->clock_out) / 60;
                        $pay = $hours * $hourlyWage;
                    }
                @endphp
                <tr>
                    <td>{{ $attendance->user->name }}</td>
                    <td>{{ $attendance->date }}</td>
                    <td>{{ $attendance->clock_in ?? '-' }}</td>
                    <td>{{ $attendance->clock_out ?? '-' }}</td>
                    <td>{{ number_format($hours, 2) }} h</td>
                    <td>{{ number_format($hourlyWage) }} 円</td>
                    <td>{{ number_format($pay) }} 円</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted">勤怠データがありません</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- 総給与 --}}
    @if($attendances->count())
        @php
            $totalPay = $attendances->sum(function($a) use ($hourlyWage) {
                if ($a->clock_in && $a->clock_out) {
                    $hours = \Carbon\Carbon::parse($a->clock_in)
                        ->diffInMinutes($a->clock_out) / 60;
                    return $hours * $hourlyWage;
                }
                return 0;
            });
        @endphp
        <div class="mt-3 text-end fw-bold">
            総給与: {{ number_format($totalPay) }} 円
        </div>
    @endif
</div>
@endsection
