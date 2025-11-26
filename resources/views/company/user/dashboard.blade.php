@extends('layouts.app')

@section('content')
<div class="container mt-4">

    <h2 class="mb-4">📊 {{ $employee->name }} さんの統計ダッシュボード</h2>

    {{-- ================================
          サマリーカード
       ================================ --}}
    <div class="row row-cols-1 row-cols-md-3 g-3 mb-4">

        {{-- 総労働時間 --}}
        <div class="col">
            <div class="card shadow-sm text-center p-3 h-100">
                <h6 class="text-secondary">⏱ 総労働時間</h6>
                <p class="display-6 mb-0 fw-bold">{{ $summary['work_hours'] }} h</p>
            </div>
        </div>

        {{-- 残業時間 --}}
        <div class="col">
            <div class="card shadow-sm text-center p-3 h-100">
                <h6 class="text-secondary">🔥 残業時間</h6>
                <p class="display-6 mb-0 fw-bold">{{ $summary['overtime'] }} h</p>
            </div>
        </div>

        {{-- 有休日数 --}}
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

    <div class="card shadow-sm mb-4 p-3">
        <h5>🟦 月別労働時間</h5>
        <canvas id="workChart"></canvas>
    </div>

    <div class="card shadow-sm mb-4 p-3">
        <h5>🟥 残業時間の推移</h5>
        <canvas id="overtimeChart"></canvas>
    </div>

    <div class="card shadow-sm mb-4 p-3">
        <h5>🟩 有休取得日数</h5>
        <canvas id="paidLeaveChart"></canvas>
    </div>

    <div class="card shadow-sm mb-4 p-3">
        <h5>🟨 給与推移</h5>
        <canvas id="salaryChart"></canvas>
    </div>

</div>
@endsection


{{-- ================================
        JS（ここが重要）
   ================================ --}}
@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const labels = @json($months);

/* 📘 労働時間 */
new Chart(document.getElementById('workChart'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: '労働時間 (h)',
            data: @json($workHours),
            borderWidth: 2,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59,130,246,0.2)',
            tension: 0.3,
        }]
    }
});

/* 🔥 残業時間 */
new Chart(document.getElementById('overtimeChart'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: '残業時間 (h)',
            data: @json($overTimes),
            borderWidth: 2,
            borderColor: '#ef4444',
            backgroundColor: 'rgba(239,68,68,0.2)',
            tension: 0.3,
        }]
    }
});

/* 🌿 有給 */
new Chart(document.getElementById('paidLeaveChart'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: '有休 (日)',
            data: @json($paidLeaveCounts),
            borderWidth: 2,
            backgroundColor: '#10b981',
        }]
    }
});

/* 💴 給与 */
new Chart(document.getElementById('salaryChart'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
           label: '給与 (円)',
            data: @json($salaryList),
            borderWidth: 2,
            backgroundColor: '#fbbf24',
        }]
    }
});
</script>
@endsection
