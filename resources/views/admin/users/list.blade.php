@extends('layouts.admin')

@section('title', $company->name . ' のユーザー一覧')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">👥 {{ $company->name }} のユーザー一覧</h1>

  <a href="{{ route('admin.companies.admin.create', $company->id) }}"
   class="btn btn-success">
    ＋ 管理者ユーザー作成
</a>



</div>


@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<table class="table table-striped align-middle">
    <thead class="table-light">
        <tr>
            <th>名前</th>
            <th>Email</th>
            <th>役割</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($users as $user)
        <tr>
            <td>{{ $user->name }}</td>
            <td>{{ $user->email }}</td>
            <td>
                @switch($user->role)
                    @case('super_admin') システム管理者 @break
                    @case('company_admin') 会社管理者 @break
                    @default スタッフ
                @endswitch
            </td>
            <td>
                <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-sm btn-outline-secondary">
                    ✏️ 編集
                </a>

                <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST"
                      class="d-inline"
                      onsubmit="return confirm('{{ $user->name }} さんを削除しますか？');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">🗑️ 削除</button>
                </form>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="4" class="text-center text-muted py-4">この会社にはユーザーがいません。</td>
        </tr>
        @endforelse
    </tbody>
</table>

<div class="mt-4">
    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">← 会社選択に戻る</a>
</div>
@endsection
