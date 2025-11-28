@extends('layouts.admin')

@section('title', 'ユーザー編集')

@section('content')
<div class="container mt-4">
    <h2>👤 ユーザー編集</h2>

    <form action="{{ route('admin.users.update', $user->id) }}" method="POST">
        @csrf
        @method('PUT')

        {{-- 名前 --}}
        <div class="mb-3">
            <label for="name" class="form-label">名前</label>
            <input type="text" name="name" id="name" class="form-control"
                value="{{ old('name', $user->name) }}" required>
        </div>

        {{-- メール --}}
        <div class="mb-3">
            <label for="email" class="form-label">メールアドレス</label>
            <input type="email" name="email" id="email" class="form-control"
                value="{{ old('email', $user->email) }}" required>
        </div>

       {{-- 権限 --}}
<div class="mb-3">
    <label for="role" class="form-label">権限</label>
    <select name="role" id="role" class="form-select" required>
        <option value="company_admin" {{ $user->role === 'company_admin' ? 'selected' : '' }}>
            🏢 会社管理者
        </option>
        <option value="employee" {{ $user->role === 'employee' ? 'selected' : '' }}>
            👤 一般社員
        </option>
    </select>
</div>


        <button type="submit" class="btn btn-primary">💾 更新</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">← 戻る</a>
    </form>

    <hr>

    {{-- ユーザー削除 --}}
    <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST"
        onsubmit="return confirm('本当にこのユーザーを削除しますか？');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger mt-3">🗑️ このユーザーを削除</button>
    </form>
</div>
@endsection
