@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="mb-4">給与一覧</h1>

    {{-- 成功メッセージ --}}
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    {{-- 勤怠から給与生成リンク --}}
    <div class="mb-3">
        <a href="{{ route('company.generatePayroll', ['id' => request()->route('id')]) }}">
            勤怠から給与を生成する
        </a>
    </div>

    {{-- フィルター --}}
    <form method="GET" action="{{ route('company.payrolls', ['id' => request()->route('id')]) }}" class="row g-3 mb-4">
        <div class="col-md-3">
            <label for="month" class="form-label">月</label>
            <input type="month" id="month" name="month" class="form-control" value="{{ request('month', now()->format('Y-m')) }}">
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

    {{-- 給与テーブル --}}
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>社員名</th>
                    <th>日付</th>
                    <th class="text-end">時給</th>
                    <th class="text-end">勤務時間</th>
                    <th class="text-end">給与</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payrolls as $payroll)
                    <tr>
                        <td>{{ $payroll->user->name }}</td>
                        <td>{{ \Carbon\Carbon::parse($payroll->month)->format('Y-m-d') }}</td>
                        <td class="text-end">{{ number_format($payroll->hourly_wage) }}円</td>
                        <td class="text-end">{{ number_format($payroll->worked_hours, 2) }}h</td>
                        <td class="text-end">{{ number_format($payroll->total_pay) }}円</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted">給与データがありません</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- 総給与 --}}
    @if($payrolls->count())
        <div class="d-flex justify-content-end mt-3">
            <div class="card bg-success text-white shadow-sm">
                <div class="card-body">
                    <strong>総給与:</strong> {{ number_format($payrolls->sum('total_pay')) }}円
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
