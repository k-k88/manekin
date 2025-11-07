@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-4">🕓 {{ $company->name }} - 勤怠追加</h2>

    {{-- メッセージ --}}
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
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

    <form action="{{ route('company.attendances.store', $company->id) }}" method="POST" class="mx-auto" style="max-width: 560px;">
        @csrf

        {{-- 社員 --}}
        <div class="mb-3">
            <label class="form-label fw-bold">👤 社員</label>
            <select name="user_id" class="form-select form-select-lg" required>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- 日付 --}}
        <div class="mb-3">
            <label class="form-label fw-bold">📅 日付</label>
            <input 
                type="date" 
                name="date" 
                class="form-control form-control-lg"
                value="{{ old('date', now()->format('Y-m-d')) }}" 
                required>
        </div>

        {{-- 出勤 / 退勤 横並び --}}
        <div class="mb-3">
            <label class="form-label fw-bold">🕒 出勤・退勤時刻</label>
            <div class="d-flex gap-3 align-items-center flex-wrap">
                <input
                    type="text"
                    name="clock_in"
                    id="clock_in"
                    class="form-control form-control-lg time-input text-center"
                    inputmode="numeric"
                    value="{{ old('clock_in') }}"
                    placeholder="出勤"
                    required>

                <span class="fw-bold">〜</span>

                <input
                    type="text"
                    name="clock_out"
                    id="clock_out"
                    class="form-control form-control-lg time-input text-center"
                    inputmode="numeric"
                    value="{{ old('clock_out') }}"
                    placeholder="退勤"
                    required>
            </div>
            <div class="text-muted small mt-2">
                0〜29時まで入力可（29:59＝翌5:59）
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <a href="{{ route('company.attendances', $company->id) }}" class="btn btn-outline-secondary btn-lg">← 戻る</a>
            <button type="submit" class="btn btn-primary btn-lg">💾 登録</button>
        </div>
    </form>
</div>

{{-- 🧠 時間入力補助 --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
    const normalizeTime = (val) => {
        if (!val) return '';
        val = val.replace(/[^\d:]/g, '').replace('：', ':').trim();

        // 「6」→「06:00」
        if (/^\d{1,2}$/.test(val)) return val.padStart(2, '0') + ':00';
        // 「730」→「07:30」
        if (/^\d{3,4}$/.test(val)) {
            let h = val.slice(0, -2);
            let m = val.slice(-2);
            h = Math.min(parseInt(h), 29).toString().padStart(2, '0');
            m = Math.min(parseInt(m), 59).toString().padStart(2, '0');
            return `${h}:${m}`;
        }
        // 「7:5」→「07:05」
        if (/^\d{1,2}:\d{1,2}$/.test(val)) {
            let [h, m] = val.split(':').map(Number);
            h = Math.min(h, 29);
            m = Math.min(m, 59);
            return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
        }
        return '';
    };

    document.querySelectorAll('.time-input').forEach(input => {
        input.addEventListener('blur', () => {
            input.value = normalizeTime(input.value);
        });
    });
});
</script>

<style>
.time-input {
    width: 120px;
    font-size: 1.05rem;
    padding: 0.5rem 0.6rem;
}
.form-control-lg, .form-select-lg {
    font-size: 1.05rem;
    padding: 0.6rem 0.75rem;
}
</style>
@endsection
