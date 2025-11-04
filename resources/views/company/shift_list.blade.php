@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2>シフト一覧</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <table class="table table-bordered mt-3">
        <thead>
            <tr>
                <th>ID</th>
                <th>従業員</th>
                <th>日付</th>
                <th>開始時間</th>
                <th>終了時間</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @foreach($shifts as $shift)
            <tr>
                <td>{{ $shift->id }}</td>
                <td>{{ $shift->user->name ?? '未登録' }}</td>
                <td>{{ $shift->date }}</td>
                <td>{{ $shift->start_time }}</td>
                <td>{{ $shift->end_time }}</td>
                <td>
                    <a href="{{ route('company.shifts.edit', $shift->id) }}" class="btn btn-sm btn-primary">編集</a>
                    <a href="{{ route('company.shifts.delete', $shift->id) }}" class="btn btn-sm btn-danger" onclick="return confirm('本当に削除しますか？')">削除</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
