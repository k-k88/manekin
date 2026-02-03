@extends('layouts.admin')

@section('title', 'ダッシュボード')

@section('content')
<h1 class="mb-4">システム全体ダッシュボード</h1>

<div class="row">
    <div class="col-md-4">
        <a href="{{ route('admin.companies.index') }}" class="text-decoration-none text-dark">
            <div class="card p-3 text-center h-100 hover-card">
                <h4>会社数</h4>
                <p class="display-6">{{ $companyCount }}</p>
            </div>
        </a>
    </div>

    <div class="col-md-4">
        <a href="{{ route('admin.users.index') }}" class="text-decoration-none text-dark">
            <div class="card p-3 text-center h-100 hover-card">
                <h4>管理者数</h4>
                <p class="display-6">{{ $adminCount }}</p>
            </div>
        </a>
    </div>

    <div class="col-md-4">
        <a href="{{ route('admin.users.index') }}" class="text-decoration-none text-dark">
            <div class="card p-3 text-center h-100 hover-card">
                <h4>ユーザー総数</h4>
                <p class="display-6">{{ $userCount }}</p>
            </div>
        </a>
    </div>
</div>
@endsection
