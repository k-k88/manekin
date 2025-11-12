@extends('layouts.company')

@section('title', '店舗一覧')

@section('content')
<div class="container">
    <h1 class="mb-4">🏬 店舗一覧（{{ $company->name }}）</h1>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="mb-3 text-end">
        <a href="{{ route('company.stores.create', $company->id) }}" class="btn btn-primary">＋ 店舗追加</a>
    </div>

    <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>店舗名</th>
                <th>住所</th>
                <th>電話番号</th>
                <th>状態</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($stores as $store)
                <tr>
                    <td>{{ $store->id }}</td>
                    <td>{{ $store->name }}</td>
                    <td>{{ $store->address ?? '-' }}</td>
                    <td>{{ $store->phone ?? '-' }}</td>
                    <td>
                        @if ($store->status === 'active')
                            <span class="badge bg-success">稼働中</span>
                        @else
                            <span class="badge bg-secondary">停止中</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('company.stores.edit', [$company->id, $store->id]) }}" class="btn btn-sm btn-outline-primary">編集</a>
                        <form action="{{ route('company.stores.destroy', [$company->id, $store->id]) }}"
                              method="POST"
                              class="d-inline"
                              onsubmit="return confirm('削除しますか？');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">削除</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-3">登録された店舗はありません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
