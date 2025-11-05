@extends('layouts.app')
 
@section('content')
<div class="container mt-4">
    <h2 class="mb-4">🕓 {{ $company->name }} - 勤怠追加</h2>
 
    {{-- ✅ メッセージ表示ブロック --}}
    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif
 
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif
 
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    {{-- ✅ ここまで追加 --}}
 
    <form action="{{ route('company.attendances.store', $company->id) }}" method="POST" class="w-50 mx-auto">
        @csrf
 
        {{-- 社員選択 --}}
        <div class="mb-3">
            <label class="form-label">👤 社員</label>
            <select name="user_id" class="form-select" required>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>
        </div>
 
        {{-- 日付 --}}
        <div class="mb-3">
            <label class="form-label">📅 日付</label>
            <input type="date" name="date" class="form-control" value="{{ old('date', now()->format('Y-m-d')) }}" required>
        </div>
 
        {{-- 出勤時刻 --}}
        <div class="mb-3">
            <label class="form-label">🕗 出勤時刻</label>
            <input
                type="text"
                name="clock_in"
                class="form-control time-input"
                placeholder="入力例：0900 または 09:00"
                value="{{ old('clock_in') }}">
        </div>
 
        {{-- 退勤時刻 --}}
        <div class="mb-3">
            <label class="form-label">🕔 退勤時刻</label>
            <input
                type="text"
                name="clock_out"
                class="form-control time-input"
                placeholder="入力例：1730 または 17:30"
                value="{{ old('clock_out') }}">
            <small class="text-muted">※ 翌日の退勤（例：22:00 → 翌日 06:00）も登録できます</small>
        </div>
 
        <button type="submit" class="btn btn-primary">💾 追加</button>
        <a href="{{ route('company.attendances', $company->id) }}" class="btn btn-secondary">← 戻る</a>
    </form>
</div>
 
{{-- 🔽 コロン自動入力＋日跨ぎ対応 --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ⏱ コロン自動入力
    document.querySelectorAll('.time-input').forEach(input => {
        input.addEventListener('input', function(e) {
            let val = e.target.value.replace(/\D/g, ''); // 数字のみ抽出
            if (val.length >= 3) {
                val = val.substring(0, 2) + ':' + val.substring(2, 4);
            }
            e.target.value = val;
        });
    });
});
</script>
@endsection
 
 