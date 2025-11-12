@extends('layouts.admin')

@section('title', '管理者ユーザー作成')

@section('content')
<h1 class="mb-4">{{ $company->name }} の管理者作成</h1>

<form action="{{ route('admin.companies.admin.store', $company->id) }}" method="POST">
    @csrf

    <div class="mb-3">
        <label>名前</label>
        <input type="text" name="name" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>メールアドレス</label>
        <input type="email" name="email" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>パスワード</label>
        <input type="password" name="password" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>パスワード確認</label>
        <input type="password" name="password_confirmation" class="form-control" required>
    </div>

    <button class="btn btn-primary">作成する</button>
</form>
@endsection
