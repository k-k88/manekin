@extends('layouts.admin')

@section('title', '会社作成')

@section('content')
<h1 class="mb-4">会社作成</h1>

<form action="{{ route('admin.companies.store') }}" method="POST">
    @csrf

    <div class="mb-3">
        <label class="form-label">会社名</label>
        <input type="text" name="name" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">会社コード（例：ABC123）</label>
        <input type="text" name="code" class="form-control" required>
        <small class="text-muted">※ 従業員が LINE で登録する時に使います。</small>
    </div>

    <button type="submit" class="btn btn-primary">登録する</button>
</form>
@endsection
