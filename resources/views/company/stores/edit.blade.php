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

        <hr>
        <h4 class="mt-4">📅 シフト提出期限設定</h4>

        <div class="mb-3">
            <label class="form-label">提出期限のタイプ</label>
           <select name="shift_deadline_type" id="deadlineType" class="form-select">
    <option value="single" @selected($store->shift_deadline_type === 'single')>一括（全体）</option>
    <option value="half" @selected($store->shift_deadline_type === 'half')>前半・後半に分ける</option>
</select>

        </div>

        <div id="singleDeadlineFields" class="mb-3">
            <label class="form-label">提出期限日（毎月◯日まで）</label>
            <input type="number" name="shift_deadline_day"
                   value="{{ old('shift_deadline_day', $store->shift_deadline_day) }}"
                   class="form-control" min="1" max="31" placeholder="例：10">
        </div>

        <div id="splitDeadlineFields" class="mb-3">
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label">前半提出期限</label>
                    <input type="number" name="shift_first_half_deadline"
                           value="{{ old('shift_first_half_deadline', $store->shift_first_half_deadline) }}"
                           class="form-control" min="1" max="31" placeholder="例：10">
                </div>
                <div class="col-md-6">
                    <label class="form-label">後半提出期限</label>
                    <input type="number" name="shift_second_half_deadline"
                           value="{{ old('shift_second_half_deadline', $store->shift_second_half_deadline) }}"
                           class="form-control" min="1" max="31" placeholder="例：25">
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-success mt-3">更新</button>
        <a href="{{ route('company.stores.index', $company) }}" class="btn btn-secondary mt-3">戻る</a>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('deadlineType');
    const singleFields = document.getElementById('singleDeadlineFields');
    const splitFields = document.getElementById('splitDeadlineFields');

    function toggleFields() {
        if (typeSelect.value === 'single') {
            singleFields.style.display = 'block';
            splitFields.style.display = 'none';
        } else {
            singleFields.style.display = 'none';
            splitFields.style.display = 'block';
        }
    }

    typeSelect.addEventListener('change', toggleFields);
    toggleFields();
});
</script>
@endsection
