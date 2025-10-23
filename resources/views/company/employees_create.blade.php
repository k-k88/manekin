@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-4">👤 {{ $company->name }} 社員登録</h2>

    <a href="{{ route('company.employees', $company->id) }}" class="btn btn-secondary mb-3">← 社員一覧へ戻る</a>

    {{-- エラーメッセージ表示 --}}
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 社員登録フォーム --}}
    <form method="POST" action="{{ route('company.employees.store', $company->id) }}">
        @csrf

        {{-- 氏名 --}}
        <div class="mb-3">
            <label class="form-label">氏名</label>
            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
        </div>

        {{-- メールアドレス --}}
        <div class="mb-3">
            <label class="form-label">メールアドレス</label>
            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
        </div>

        {{-- 電話番号 --}}
        <div class="mb-3">
            <label class="form-label">電話番号</label>
            <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
        </div>

        {{-- 所属店舗 --}}
        <div class="mb-3">
            <label class="form-label">所属店舗</label>
            <select name="store_id" class="form-select" required>
                <option value="">店舗を選択</option>
                @foreach($stores as $store)
                    <option value="{{ $store->id }}" {{ old('store_id') == $store->id ? 'selected' : '' }}>
                        {{ $store->name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- 入社日 --}}
        <div class="mb-3">
            <label class="form-label">入社日</label>
            <input type="date" name="hire_date" class="form-control" value="{{ old('hire_date') }}" required>
        </div>

        {{-- 役職 --}}
        <div class="mb-3">
            <label class="form-label">役職</label>
            <select name="role" class="form-select" required>
                <option value="employee" {{ old('role') == 'employee' ? 'selected' : '' }}>一般社員</option>
                <option value="manager" {{ old('role') == 'manager' ? 'selected' : '' }}>店長・管理者</option>
                <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>企業管理者</option>
            </select>
        </div>

        {{-- パスワード --}}
        <div class="mb-3">
            <label class="form-label">パスワード</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        {{-- パスワード確認 --}}
        <div class="mb-3">
            <label class="form-label">パスワード確認</label>
            <input type="password" name="password_confirmation" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary">登録する</button>
    </form>
</div>
@endsection
