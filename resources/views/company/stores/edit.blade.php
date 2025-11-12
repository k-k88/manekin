@extends('layouts.company')

@section('title', '店舗編集')

@section('content')
<div class="container">
    <h1 class="mb-4">店舗情報を編集</h1>

    <!-- エラーメッセージ -->
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- 更新フォーム -->
    <form action="{{ route('company.stores.update', [$company, $store]) }}" method="POST" class="mb-4">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="name" class="form-label">店舗名 <span class="text-danger">*</span></label>
            <input type="text" name="name" id="name" class="form-control" required value="{{ old('name', $store->name) }}">
        </div>

        <div class="mb-3">
            <label for="address" class="form-label">住所</label>
            <input type="text" name="address" id="address" class="form-control" value="{{ old('address', $store->address) }}">
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">電話番号</label>
            <input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone', $store->phone) }}">
        </div>

        <div class="mb-3">
            <label for="status" class="form-label">状態</label>
            <select name="status" id="status" class="form-select">
                <option value="active" {{ old('status', $store->status) === 'active' ? 'selected' : '' }}>稼働中</option>
                <option value="inactive" {{ old('status', $store->status) === 'inactive' ? 'selected' : '' }}>停止中</option>
            </select>
        </div>

        <button type="submit" class="btn btn-success">更新</button>
        <a href="{{ route('company.stores.index', $company) }}" class="btn btn-secondary">戻る</a>
    </form>

    <!-- 削除フォーム -->
    <div class="border-top pt-3">
        <h5 class="text-danger mb-3">⚠ この店舗を削除する</h5>
        <form action="{{ route('company.stores.destroy', [$company, $store]) }}" method="POST" onsubmit="return confirm('本当に削除しますか？ この操作は元に戻せません。');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">店舗を削除する</button>
        </form>
    </div>
</div>
@endsection
