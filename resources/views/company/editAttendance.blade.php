@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-4">✏️ {{ $company->name }} 勤怠編集</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('company.attendances.update', ['company' => $company->id, 'attendance' => $attendance->id]) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label>社員名</label>
            <input type="text" class="form-control" value="{{ $attendance->user->name }}" disabled>
        </div>

        <div class="mb-3">
            <label>日付</label>
            <input type="date" class="form-control" value="{{ $attendance->date }}" disabled>
        </div>

        <div class="mb-3">
            <label>出勤時刻</label>
            <input type="time" name="clock_in" class="form-control" value="{{ $attendance->clock_in }}">
        </div>

        <div class="mb-3">
            <label>退勤時刻</label>
            <input type="time" name="clock_out" class="form-control" value="{{ $attendance->clock_out }}">
        </div>

        <button type="submit" class="btn btn-primary">更新</button>
        <a href="{{ route('company.attendances', ['company' => $company->id]) }}" class="btn btn-secondary">戻る</a>
    </form>
</div>
@endsection
