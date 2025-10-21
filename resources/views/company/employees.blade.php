{{-- resources/views/company/employees.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-4">👥 {{ $company->name }} 社員一覧</h2>

    <div class="mb-3">
        <a href="{{ route('company.dashboard', $company->id) }}" class="btn btn-secondary">← ダッシュボードに戻る</a>
    </div>

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
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted">社員が登録されていません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
