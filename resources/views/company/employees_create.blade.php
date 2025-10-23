@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-4">👤 {{ $company->name }} 社員登録</h2>

    <a href="{{ route('company.employees', $company->id) }}" class="btn btn-secondary mb-3">← 社員一覧へ戻る</a>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('company.employees.store', $company->id) }}">
        @csrf

        <div class="mb-3">
            <label class="form-label">氏名</label>
            <input type="text" name="name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">メールアドレス</label>
            <input type="email" name="email" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">電話番号</label>
            <input type="text" name="phone" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">役職</label>
            <select name="role" class="form-select" required>
                <option value="employee">一般社員</option>
                <option value="manager">店長・管理者</option>
                <option value="admin">企業管理者</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">パスワード</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">パスワード確認</label>
            <input type="password" name="password_confirmation" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary">登録する</button>
    </form>
</div>
@endsection
