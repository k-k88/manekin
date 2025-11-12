@extends('layouts.admin')

@section('title', $company->name . ' のユーザー一覧')

@section('content')
<h1>{{ $company->name }} のユーザー一覧</h1>

<table class="table mt-4">
    <thead>
        <tr>
            <th>名前</th>
            <th>Email</th>
            <th>役割</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($users as $user)
        <tr>
            <td>{{ $user->name }}</td>
            <td>{{ $user->email }}</td>
            <td>{{ $user->role }}</td>
            <td>
                <a href="#" class="btn btn-sm btn-outline-secondary">編集</a>
                <a href="#" class="btn btn-sm btn-outline-danger">削除</a>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

<a href="{{ route('admin.users.index') }}" class="btn btn-secondary mt-3">← 会社選択に戻る</a>

@endsection
