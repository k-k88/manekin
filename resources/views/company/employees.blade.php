@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">👥 {{ $company->name }} 社員一覧</h2>
        <a href="{{ route('company.employees.create', $company->id) }}" class="btn btn-success">
            ＋ 社員登録
        </a>
    </div>

    <div class="mb-3">
        <a href="{{ route('company.dashboard', $company->id) }}" class="btn btn-secondary">
            ← ダッシュボードに戻る
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <table class="table table-bordered table-hover align-middle shadow-sm">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>氏名</th>
                <th>メールアドレス</th>
                <th>電話番号</th>
                <th>役割</th>
                <th>所属店舗</th>
                <th>入社日</th>
                <th>ステータス</th>
                <th>時給</th> {{-- ✅ 追加 --}}
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employees as $employee)
                <tr>
                    <td>{{ $employee->id }}</td>
                    <td>{{ $employee->name }}</td>
                    <td>{{ $employee->email ?? '-' }}</td>
                    <td>{{ $employee->phone ?? '-' }}</td>
                    <td>
                        @if ($employee->role === 'company_admin')
                            <span class="badge bg-danger">管理者</span>
                        @elseif ($employee->role === 'manager')
                            <span class="badge bg-warning text-dark">店長</span>
                        @else
                            <span class="badge bg-primary">従業員</span>
                        @endif
                    </td>
                    <td>{{ $employee->store->name ?? '-' }}</td>
                    <td>{{ $employee->hire_date ?? '-' }}</td>
                    <td>
                        @if ($employee->status === 'active')
                            <span class="badge bg-success">在籍中</span>
                        @else
                            <span class="badge bg-secondary">退職</span>
                        @endif
                    </td>

                    {{-- ✅ 時給フォーム --}}
                    <td>
                        <form action="{{ route('company.employees.updateWage', ['company' => $company->id, 'employee' => $employee->id]) }}" 
                              method="POST" class="d-flex align-items-center">
                            @csrf
                            @method('PUT')
                            <input type="number" name="hourly_wage" 
                                   value="{{ $employee->hourly_wage ?? '' }}" 
                                   class="form-control form-control-sm me-2 text-end" 
                                   style="width:90px;" placeholder="円">
                            <button type="submit" class="btn btn-sm btn-outline-primary">更新</button>
                        </form>
                    </td>

                    <td class="d-flex gap-1">
                        <a href="{{ route('company.employees.edit', ['company' => $company->id, 'employee' => $employee->id]) }}" 
                           class="btn btn-sm btn-warning">編集</a>

                        <form action="{{ route('company.employees.delete', ['company' => $company->id, 'employee' => $employee->id]) }}"
                              method="POST" onsubmit="return confirm('本当に削除しますか？')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger">削除</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center text-muted">社員が登録されていません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
