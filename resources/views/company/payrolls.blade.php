@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="mb-4">給与一覧</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="GET" class="row mb-3">
        <div class="col-md-3">
            <label for="month" class="form-label">月</label>
            <input type="month" id="month" name="month" class="form-control"
                   value="{{ request('month') }}">
        </div>
        <div class="col-md-3">
            <label for="user_id" class="form-label">社員</label>
            <select id="user_id" name="user_id" class="form-select">
                <option value="">全員</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" @if(request('user_id') == $user->id) selected @endif>
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 align-self-end">
            <button type="submit" class="btn btn-primary">絞り込み</button>
            <a href="{{ route('company.payrolls', ['id' => request()->route('id')]) }}" class="btn btn-secondary">リセット</a>
        </div>
    </form>

    <a href="{{ route('company.generatePayroll', ['id' => request()->route('id')]) }}" class="btn btn-success mb-3">
        勤怠から給与を生成する
    </a>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>社員名</th>
                <th>月</th>
                <th>時給</th>
                <th>勤務時間</th>
                <th>給与</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payrolls as $payroll)
                <tr>
                    <td>{{ $payroll->user->name }}</td>
                    <td>{{ \Carbon\Carbon::parse($payroll->month)->format('Y-m') }}</td>
                    <td>{{ number_format($payroll->hourly_wage) }}円</td>
                    <td>{{ number_format($payroll->total_hours, 2) }}h</td>
                    <td>{{ number_format($payroll->total_pay) }}円</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">給与データがありません</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($payrolls->count())
        <div class="mt-3 text-end fw-bold">
            総給与: {{ number_format($payrolls->sum('total_pay')) }}円
        </div>
    @endif
</div>
@endsection
