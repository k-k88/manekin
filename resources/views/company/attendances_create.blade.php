@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2>{{ $company->name }} - 勤怠追加</h2>

    <form action="{{ route('company.attendances.store', $company->id) }}" method="POST" class="w-50 mx-auto">
        @csrf
        <div class="mb-3">
            <label>社員</label>
            <select name="user_id" class="form-select">
                @foreach($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label>日付</label>
            <input type="date" name="date" class="form-control" value="{{ old('date', now()->format('Y-m-d')) }}">
        </div>
        <div class="mb-3">
            <label>出勤時刻</label>
            <input type="time" name="clock_in" class="form-control" value="{{ old('clock_in') }}">
        </div>
        <div class="mb-3">
            <label>退勤時刻</label>
            <input type="time" name="clock_out" class="form-control" value="{{ old('clock_out') }}">
        </div>
        <button type="submit" class="btn btn-primary">追加</button>
    </form>
</div>
@endsection
