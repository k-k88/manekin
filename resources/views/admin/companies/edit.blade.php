@extends('layouts.admin')

@section('title', '会社情報の編集')

@section('content')
<div class="container mt-4">
    <h2>🏢 会社情報の編集</h2>

    <form action="{{ route('admin.companies.update', $company->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="name" class="form-label">会社名</label>
            <input type="text" name="name" id="name" class="form-control"
                   value="{{ old('name', $company->name) }}" required>
        </div>

        <div class="mb-3">
            <label for="code" class="form-label">企業コード</label>
            <input type="text" name="code" id="code" class="form-control"
                   value="{{ old('code', $company->code) }}" required>
        </div>

        <button type="submit" class="btn btn-primary">💾 更新</button>
        <a href="{{ route('admin.companies.index') }}" class="btn btn-secondary">← 戻る</a>
    </form>

    <hr>

    <form action="{{ route('admin.companies.destroy', $company->id) }}" method="POST"
          onsubmit="return confirm('本当にこの会社を削除しますか？\n全ての店舗・社員データも削除される可能性があります。');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger mt-3">🗑️ この会社を削除</button>
    </form>
</div>
@endsection
