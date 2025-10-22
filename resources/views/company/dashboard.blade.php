{{-- resources/views/company/dashboard.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-4">🏢 {{ $company->name }} 管理ダッシュボード</h2>

    <div class="row">
        <!-- 勤怠集計 -->
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">本日の出勤数</h5>
                    <p class="display-6">{{ $today_attendance_count }} 人</p>
                </div>
            </div>
        </div>

        <!-- 登録社員数 -->
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">登録社員数</h5>
                    <p class="display-6">{{ $employee_count }} 人</p>
                </div>
            </div>
        </div>

        <!-- 店舗数 -->
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">登録店舗数</h5>
                    <p class="display-6">{{ $store_count }} 店舗</p>
                </div>
            </div>
        </div>
    </div>

    <hr>

    <div class="text-center">
        <a href="{{ route('company.employees', $company->id) }}" class="btn btn-primary m-2">👥 社員一覧</a>
        <a href="{{ route('company.attendances', $company->id) }}" class="btn btn-success m-2">🕓 勤怠一覧</a>
        <a href="{{ route('company.payrolls', $company->id) }}" class="btn btn-warning m-2">💰 給与一覧</a>
    </div>
</div>
@endsection
