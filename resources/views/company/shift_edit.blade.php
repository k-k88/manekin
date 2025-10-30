@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2>シフト編集</h2>

    <form action="{{ route('company.shifts.update', $shift->id) }}" method="POST">
        @csrf

        <div class="mb-3">
            <label for="user_id" class="form-label">従業員</label>
            <select name="user_id" class="form-control">
                @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ $user->id == $shift->user_id ? 'selected' : '' }}>
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label for="date" class="form-label">日付</label>
            <input type="date" class="form-control" name="date" value="{{ $shift->date }}" required>
        </div>

        <div class="mb-3">
            <label for="start_time" class="form-label">開始時間</label>
            <input type="time" class="form-control" name="start_time" value="{{ $shift->start_time }}" required>
        </div>

        <div class="mb-3">
            <label for="end_time" class="form-label">終了時間</label>
            <input type="time" class="form-control" name="end_time" value="{{ $shift->end_time }}" required>
        </div>

        <button type="submit" class="btn btn-primary w-100">更新</button>
    </form>
</div>
@endsection
