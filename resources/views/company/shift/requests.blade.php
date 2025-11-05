{{-- resources/views/company/shift/requests.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container mt-4">

    <h3 class="mb-4">📨 シフト提出一覧（承認待ち）</h3>

    @if($requests->isEmpty())
        <div class="alert alert-warning">提出中のシフトはありません。</div>
    @else
        <table class="table table-bordered align-middle">
            <thead class="table-light text-center">
                <tr>
                    <th>社員名</th>
                    <th>日付</th>
                    <th>内容</th>
                    <th>状態</th>
                </tr>
            </thead>
            <tbody>
            @foreach($requests as $req)
                <tr>
                    <td>{{ $req->user->name }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($req->shift_date)->format('Y/m/d') }}</td>

                    <td class="text-center">
                        @if($req->is_day_off)
                            <span class="text-danger fw-bold">❌ 希望休</span>
                        @else
                            {{ \Carbon\Carbon::parse($req->start_time)->format('H:i') }}
                             〜
                            {{ \Carbon\Carbon::parse($req->end_time)->format('H:i') }}
                        @endif
                    </td>

                    <td class="text-center">
                        <span class="badge bg-primary">{{ $req->status }}</span>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <div class="text-center mt-4">
        <a href="{{ route('company.dashboard', $company->id) }}" class="btn btn-secondary">← ダッシュボードへ戻る</a>
    </div>

</div>
@endsection
