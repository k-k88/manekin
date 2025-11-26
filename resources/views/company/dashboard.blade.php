{{-- resources/views/company/dashboard.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-4">🏢 {{ $company->name }} 管理ダッシュボード</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">

        <!-- 本日の出勤数（クリック可能） -->
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm text-center"
                data-bs-toggle="modal"
                data-bs-target="#todayAttendancesModal"
                style="cursor: pointer;">
                <div class="card-body">
                    <h5 class="card-title">本日の出勤数</h5>
                    <p class="display-6">{{ $today_attendance_count }} 人</p>
                </div>
            </div>
        </div>

        <!-- 登録社員数 -->
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm text-center">
                <div class="card-body">
                    <h5 class="card-title">登録社員数</h5>
                    <p class="display-6">{{ $employee_count }} 人</p>
                </div>
            </div>
        </div>

        <!-- 登録店舗数 -->
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm text-center">
                <div class="card-body">
                    <h5 class="card-title">登録店舗数</h5>
                    <p class="display-6">{{ $store_count }} 店舗</p>
                    <a href="{{ route('company.stores.index', $company->id) }}" class="btn btn-outline-secondary mt-2">
                        🏬 店舗管理へ
                    </a>
                </div>
            </div>
        </div>
    </div>

    <hr>

    {{-- 🔧 締め日設定フォーム --}}
    <h4 class="mt-4">🔧 締め日設定</h4>

    <form action="{{ route('company.update', $company->id) }}" method="POST" class="mb-4">
        @csrf
        @method('PUT')

        <div class="d-flex align-items-center gap-2 flex-wrap" style="max-width: 220px;">
            <select name="closing_day" class="form-select w-auto">
                @for ($i = 1; $i <= 31; $i++)
                    <option value="{{ $i }}" {{ $company->closing_day == $i ? 'selected' : '' }}>
                        {{ $i }}日
                    </option>
                @endfor
            </select>
            <button type="submit" class="btn btn-primary">更新</button>
        </div>
    </form>

    <h4 class="mt-4 mb-3">🕓 出退勤履歴（📅 日付指定 & 🔁 自動更新）</h4>

    <div class="d-flex align-items-center mb-3 gap-2">
        <input type="date" id="attendance-date" class="form-control w-auto"
            value="{{ \Carbon\Carbon::today()->format('Y-m-d') }}">
        <button id="load-logs" class="btn btn-outline-primary">📅 表示</button>
    </div>

    <div style="max-height: 200px; overflow-y: auto;" class="card shadow-sm" id="attendance-log">
        @include('company.partials.recent_logs')
    </div>

    <div class="text-center mt-4">
        <a href="{{ route('company.employees', $company->id) }}" class="btn btn-primary m-2">👥 社員一覧</a>
        <a href="{{ route('company.attendances', $company->id) }}" class="btn btn-success m-2">🕓 勤怠一覧</a>
        <a href="{{ route('company.payrolls', $company->id) }}" class="btn btn-warning m-2">💰 給与一覧</a>
        <a href="{{ route('company.shift.requests', $company->id) }}" class="btn btn-info m-2">📨 シフト提出一覧</a>
        <a href="{{ route('company.attendances.late_early', $company->id) }}" class="btn btn-danger m-2">⏰ 遅刻・早退一覧</a>

        <!-- 直接シフト編集画面へリンク -->
        <a href="{{ route('company.shift.calendar.edit', ['company' => $company->id]) }}" class="btn btn-secondary m-2">
            🗓️ シフト調整
        </a>
    </div>
</div>


<!-- ーーーーーーー 今日の出勤者モーダル ーーーーーーー -->
<div class="modal fade" id="todayAttendancesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">本日の出勤者一覧</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body" id="today-attendances-body">
        読み込み中...
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

    loadButton.addEventListener('click', fetchLogs);
    setInterval(fetchLogs, 30000); // 30秒ごとに更新

    // ★ 今日の出勤者モーダル読み込み
    const todayModal = document.getElementById('todayAttendancesModal');
    todayModal.addEventListener('show.bs.modal', async () => {
        const res = await fetch("{{ route('company.todayAttendances', $company->id) }}");
        const html = await res.text();
        document.getElementById('today-attendances-body').innerHTML = html;
    });
});
</script>

@endsection
