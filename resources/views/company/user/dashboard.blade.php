@extends('layouts.app')

@section('content')
<div class="container mt-4">

    <h2 class="mb-4">📊 {{ $employee->name }} さんの統計ダッシュボード</h2>

    {{-- ========================================
            月選択セレクト（サマリー用）
    ========================================= --}}
    <form method="GET" class="mb-3">
        <select name="month" class="form-select w-auto" onchange="this.form.submit()">
            @foreach($months as $index => $m)
                @php
                    // '2025/01' → '2025-01'
                    $val = \Carbon\Carbon::createFromFormat('Y/m', $m)->format('Y-m');
                @endphp
                <option value="{{ $val }}" {{ $selectedMonth == $val ? 'selected' : '' }}>
                    {{ $m }}
                </option>
            @endforeach
        </select>
    </form>

    {{-- ========================================
            サマリーカード（選択月で変動）
    ========================================= --}}
    <div class="row row-cols-1 row-cols-md-3 g-3 mb-4">

        {{-- 総労働時間 --}}
        <div class="col">
            <div class="card shadow-sm text-center p-3 h-100">
                <h6 class="text-secondary">⏱ 総労働時間</h6>
                <p class="display-6 mb-0 fw-bold">{{ $summary['work_hours'] }} h</p>
            </div>
        </div>

        {{-- 残業 --}}
        <div class="col">
            <div class="card shadow-sm text-center p-3 h-100">
                <h6 class="text-secondary">🔥 残業時間</h6>
                <p class="display-6 mb-0 fw-bold">{{ $summary['overtime'] }} h</p>
            </div>
        </div>

        {{-- 有休 --}}
        <div class="col">
            <div class="card shadow-sm text-center p-3 h-100">
                <h6 class="text-secondary">🌿 有休日数</h6>
                <p class="display-6 mb-0 fw-bold">{{ $summary['paid_leave'] }} 日</p>
            </div>
        </div>

        {{-- 勤務回数 --}}
        <div class="col">
            <div class="card shadow-sm text-center p-3 h-100">
                <h6 class="text-secondary">🧑‍💻 勤務回数</h6>
                <p class="display-6 mb-0 fw-bold">{{ $summary['work_count'] }} 回</p>
            </div>
        </div>

        {{-- 平均労働時間 --}}
        <div class="col">
            <div class="card shadow-sm text-center p-3 h-100">
                <h6 class="text-secondary">📘 平均労働時間</h6>
                <p class="display-6 mb-0 fw-bold">{{ $summary['avg_work_hours'] }} h</p>
            </div>
        </div>

    </div>

    {{-- ========================================
            ▼ グラフ選択
    ========================================= --}}
    <div class="card shadow-sm p-3 mb-4">
        <label class="fw-bold">表示するグラフを選択：</label>
        <select id="chartSelector" class="form-select mt-2">
            <option value="work">🟦 月別労働時間</option>
            <option value="overtime">🟥 残業時間の推移</option>
            <option value="paidLeave">🟩 有休取得日数</option>
            <option value="salary">🟨 給与推移</option>
        </select>
    </div>

    {{-- ========================================
            ▼ グラフ表示（ズレない/iPhone風アニメ）
    ========================================= --}}
    <style>
        .chart-box {
            display: none;
            opacity: 0;
            transform: scale(0.97);
            transition: opacity .35s ease, transform .35s ease;
        }
        .chart-box.active {
            opacity: 1;
            transform: scale(1);
        }
    </style>

    {{-- 労働時間：初期表示 --}}
    <div id="chart-work" class="chart-box active card shadow-sm mb-4 p-3" style="display:block;">
        <h5>🟦 月別労働時間</h5>
        <canvas id="workChart"></canvas>
    </div>

    {{-- 残業 --}}
    <div id="chart-overtime" class="chart-box card shadow-sm mb-4 p-3">
        <h5>🟥 残業時間の推移</h5>
        <canvas id="overtimeChart"></canvas>
    </div>

    {{-- 有休 --}}
    <div id="chart-paidLeave" class="chart-box card shadow-sm mb-4 p-3">
        <h5>🟩 有休取得日数</h5>
        <canvas id="paidLeaveChart"></canvas>
    </div>

    {{-- 給与 --}}
    <div id="chart-salary" class="chart-box card shadow-sm mb-4 p-3">
        <h5>🟨 給与推移</h5>
        <canvas id="salaryChart"></canvas>
    </div>

</div>
@endsection


{{-- ========================================
        Chart.js + 切り替えアニメ
======================================== --}}
@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const labels = @json($months);

// ======= グラフ描画 =======
new Chart(document.getElementById('workChart'), {
    type: 'line',
    data: { labels, datasets: [{
        label: '労働時間 (h)',
        data: @json($workHours),
        borderColor: '#3b82f6',
        backgroundColor: 'rgba(59,130,246,0.2)',
        borderWidth: 2,
        tension: 0.3,
    }] }
});

new Chart(document.getElementById('overtimeChart'), {
    type: 'line',
    data: { labels, datasets: [{
        label: '残業時間 (h)',
        data: @json($overTimes),
        borderColor: '#ef4444',
        backgroundColor: 'rgba(239,68,68,0.2)',
        borderWidth: 2,
        tension: 0.3,
    }] }
});

new Chart(document.getElementById('paidLeaveChart'), {
    type: 'bar',
    data: { labels, datasets: [{
        label: '有休 (日)',
        data: @json($paidLeaveCounts),
        backgroundColor: '#10b981',
        borderWidth: 2,
    }] }
});

new Chart(document.getElementById('salaryChart'), {
    type: 'bar',
    data: { labels, datasets: [{
        label: '給与 (円)',
        data: @json($salaryList),
        backgroundColor: '#fbbf24',
        borderWidth: 2,
    }] }
});

// ======= ズレないiPhoneアニメ切り替え =======
document.getElementById('chartSelector').addEventListener('change', function () {

    const selected = this.value;

    document.querySelectorAll('.chart-box').forEach(box => {
        box.classList.remove('active');
        box.style.display = 'none';
    });

    const target = document.getElementById(`chart-${selected}`);
    target.style.display = 'block';

    requestAnimationFrame(() => {
        target.classList.add('active');
    });
});
</script>
@endsection
