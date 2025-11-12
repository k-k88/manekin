@extends('layouts.admin')

@section('title', 'ユーザー管理')

@section('content')
<h1 class="mb-4">ユーザー管理</h1>

<div class="card p-4">
    <form action="{{ route('admin.users.byCompany') }}" method="GET">
        <div class="mb-3">
            <label class="form-label">会社を選択</label>
            <select name="company_id" class="form-select" required>
                <option value="">-- 選択してください --</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->name }} ({{ $company->code }})</option>
                @endforeach
            </select>
        </div>

        <button class="btn btn-primary">表示</button>
    </form>
</div>
@endsection
