@extends('layouts.admin')

@section('title', 'ダッシュボード')

@section('content')
<h1 class="mb-4">システム全体ダッシュボード</h1>

<div class="row">
    <div class="col-md-4">
        <div class="card p-3 text-center">
            <h4>会社数</h4>
            <p class="display-6">{{ $companyCount }}</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 text-center">
            <h4>管理者数</h4>
            <p class="display-6">{{ $adminCount }}</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 text-center">
            <h4>ユーザー総数</h4>
            <p class="display-6">{{ $userCount }}</p>
        </div>
    </div>
</div>
@endsection
