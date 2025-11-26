@extends('layouts.app')

@section('title', '店舗登録')

@section('content')
<div class="container">
    <h1 class="mb-4">店舗を新しく登録</h1>

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

    <form action="{{ route('company.stores.store', $company) }}" method="POST">
        @csrf

        <div class="mb-3">
            <label for="name" class="form-label">店舗名 <span class="text-danger">*</span></label>
            <input type="text" name="name" id="name" class="form-control" required value="{{ old('name') }}">
        </div>

        <div class="mb-3">
            <label for="address" class="form-label">住所</label>
            <input type="text" name="address" id="address" class="form-control" value="{{ old('address') }}">
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">電話番号</label>
            <input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone') }}">
        </div>

        <div class="mb-3">
            <label for="status" class="form-label">状態</label>
            <select name="status" id="status" class="form-select">
                <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>稼働中</option>
                <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>停止中</option>
            </select>
        </div>

        <button type="submit" class="btn btn-success">登録</button>
        <a href="{{ route('company.stores.index', $company) }}" class="btn btn-secondary">戻る</a>
    </form>
</div>
@endsection
