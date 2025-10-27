@extends('layouts.app')

@section('content')
<div class="container">
    <h1>社員情報の編集</h1>

    <form method="POST" action="{{ route('company.employees.update', ['company' => $company->id, 'employee' => $employee->id]) }}">
        @csrf

        {{-- 氏名 --}}
        <div class="mb-3">
            <label class="form-label">氏名</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $employee->name) }}" required>
        </div>

        {{-- メール --}}
        <div class="mb-3">
            <label class="form-label">メールアドレス</label>
            <input type="email" name="email" class="form-control" value="{{ old('email', $employee->email) }}" required>
        </div>

        {{-- 電話番号 --}}
        <div class="mb-3">
            <label class="form-label">電話番号</label>
            <input type="text" name="phone" class="form-control" value="{{ old('phone', $employee->phone) }}">
        </div>

        {{-- 所属店舗 --}}
        <div class="mb-3">
            <label class="form-label">所属店舗</label>
            <select name="store_id" class="form-select" required>
                @foreach($stores as $store)
                    <option value="{{ $store->id }}" {{ old('store_id', $employee->store_id) == $store->id ? 'selected' : '' }}>
                        {{ $store->name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- 入社日 --}}
        <div class="mb-3">
            <label class="form-label">入社日</label>
            <input type="date" name="hire_date" class="form-control" value="{{ old('hire_date', $employee->hire_date) }}" required>
        </div>

        <button type="submit" class="btn btn-primary">更新する</button>
        <a href="{{ route('company.employees', $company->id) }}" class="btn btn-secondary">戻る</a>
    </form>
</div>
@endsection
