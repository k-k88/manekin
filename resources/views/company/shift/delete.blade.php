@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2>❌ シフト削除</h2>
    <p>{{ $company->name }} のシフトデータを削除します</p>

    <form action="{{ route('company.shift.delete', $company->id) }}" method="POST" class="mt-3">
        @csrf
        @method('DELETE')

        <label class="form-label">🗓 対象月</label>
        <input type="month" name="month" class="form-control" required>

        <button type="submit" class="btn btn-danger mt-3">
            ❌ シフトを削除する
        </button>

        <a href="{{ route('company.dashboard', $company->id) }}" class="btn btn-secondary mt-3">
            ← 戻る
        </a>
    </form>
</div>
@endsection
