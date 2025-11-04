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
 
    <h4 class="mt-4 mb-3">🕓 出退勤履歴（📅 日付指定 & 🔁 自動更新）</h4>
 
    <!-- 📅 日付選択＋更新ボタン -->
    <div class="d-flex align-items-center mb-3 gap-2">
        <input type="date" id="attendance-date" class="form-control w-auto"
            value="{{ \Carbon\Carbon::today()->format('Y-m-d') }}">
        <button id="load-logs" class="btn btn-outline-primary">📅 表示</button>
    </div>
 
    <!-- 出退勤ログ表示エリア -->
    <div style="max-height: 200px; overflow-y: auto;" class="card shadow-sm" id="attendance-log">
        @include('company.partials.recent_logs')
    </div>
 
    <div class="text-center mt-4">
        <a href="{{ route('company.employees', $company->id) }}" class="btn btn-primary m-2">👥 社員一覧</a>
        <a href="{{ route('company.attendances', $company->id) }}" class="btn btn-success m-2">🕓 勤怠一覧</a>
        <a href="{{ route('company.payrolls', $company->id) }}" class="btn btn-warning m-2">💰 給与一覧</a>
           <!-- 🗓️ シフト調整ボタン（モーダルを開く） -->
    <button type="button" class="btn btn-info m-2" data-bs-toggle="modal" data-bs-target="#shiftModal">
        🗓️ シフト調整
    </button>
    </div>
</div>
 
<!-- 🗓️ シフト調整モーダル -->
<div class="modal fade" id="shiftModal" tabindex="-1" aria-labelledby="shiftModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg">
      <div class="modal-header">
        <h5 class="modal-title" id="shiftModalLabel">シフト調整メニュー</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
      </div>
      <div class="modal-body text-center">
        <p>以下の操作を選択してください。</p>
 
        <a href="{{ route('company.shifts.edit', $company->id) }}" class="btn btn-primary m-2">
            ✏️ シフト編集
        </a>
 
        <a href="{{ route('company.shifts.delete', $company->id) }}" class="btn btn-danger m-2">
            ❌ シフト削除
        </a>
      </div>
    </div>
  </div>
</div>
 
<script>
document.addEventListener('DOMContentLoaded', function() {
    const logContainer = document.getElementById('attendance-log');
    const dateInput = document.getElementById('attendance-date');
    const loadButton = document.getElementById('load-logs');
 
    async function fetchLogs() {
        const date = dateInput.value;
        const response = await fetch("{{ route('company.recentLogs', $company->id) }}?date=" + date);
        const html = await response.text();
        logContainer.innerHTML = html;
    }
 
    // ✅ ボタンクリックで更新
    loadButton.addEventListener('click', fetchLogs);
 
    // ✅ 30秒ごとに自動更新
    setInterval(fetchLogs, 30000);
});
</script>
@endsection
 
 