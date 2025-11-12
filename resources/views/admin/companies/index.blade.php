@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between mb-4">
        <h3>🏢 会社一覧</h3>
        <a href="{{ route('admin.companies.create') }}" class="btn btn-primary">＋ 新規会社追加</a>
    </div>

    <table class="table table-bordered table-hover align-middle text-center">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>会社名</th>
                <th>管理者数</th>
                <th>従業員数</th>
                <th width="180">操作</th>
            </tr>
        </thead>
        <tbody>
            @foreach($companies as $company)
                <tr>
                    <td>{{ $company->id }}</td>
                    <td>{{ $company->name }}</td>
                    <td>{{ $company->admin_count }}</td>
                    <td>{{ $company->user_count }}</td>
                    <td>
                        <a href="{{ route('admin.companies.edit', $company) }}" class="btn btn-sm btn-warning">編集</a>

                        <form action="{{ route('admin.companies.destroy', $company) }}" method="POST" class="d-inline" 
                              onsubmit="return confirm('本当に削除しますか？この操作は取り消せません。');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger">削除</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
