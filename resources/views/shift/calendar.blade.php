{{-- resources/views/shift/calendar.blade.php --}}
@extends('layouts.shift')

@section('content')
<div class="container py-2 px-2 px-sm-4">
    <h3 class="mb-3 text-center">📅 {{ $user->name }} さんのシフト登録</h3>

    <input type="hidden" id="shift_user_id" value="{{ $user->id }}">
    <input type="hidden" id="deadlinePassed" value="{{ $deadlinePassed ? '1' : '0' }}">
    <input type="hidden" id="lockedDates" value='@json($lockedDates)'>
    <input type="hidden" id="currentYear" value="{{ $year }}">
    <input type="hidden" id="currentMonth" value="{{ $month }}">

    @php
        $current = \Carbon\Carbon::create($year, $month, 1);
        $prev = $current->copy()->subMonth();
        $next = $current->copy()->addMonth();
    @endphp

    {{-- 月移動 --}}
    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap text-center">
        <a href="{{ route('shift.user.calendar', ['user'=>$user->id, 'year'=>$prev->year, 'month'=>$prev->month]) }}" 
           class="btn btn-outline-secondary btn-sm mb-2">← 前月</a>

        <h4 class="mb-2">{{ $year }}年 {{ $month }}月</h4>

        <a href="{{ route('shift.user.calendar', ['user'=>$user->id, 'year'=>$next->year, 'month'=>$next->month]) }}" 
           class="btn btn-outline-secondary btn-sm mb-2">次月 →</a>
    </div>

    {{-- 締切情報 --}}
    <div class="alert alert-info py-2 small mb-3">
        <strong>📌 締切設定：</strong>
        @if ($user->store && $user->store->shift_deadline_type === 'split')
            前半 {{ $user->store->shift_first_half_deadline ?? '10' }}日 ／ 
            後半 {{ $user->store->shift_second_half_deadline ?? '25' }}日
        @else
            毎月 {{ $user->store->shift_deadline_day ?? '10' }}日まで
        @endif
    </div>

    {{-- カレンダー --}}
    <div class="table-responsive-sm shadow-sm">
        <table class="table table-bordered text-center align-middle mb-0" style="min-width: 600px;">
            <thead class="table-light">
                <tr>
                    @foreach(['日','月','火','水','木','金','土'] as $d)
                        <th>{{ $d }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
            @php $day = 1 - $firstDay->dayOfWeek; @endphp
            @while($day <= $lastDay->day)
                <tr>
                    @for($i=0; $i<7; $i++)
                        @php $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day); @endphp

                        @if($day < 1 || $day > $lastDay->day)
                            <td></td>
                        @else
                            @php
                                $confirmedShift = $confirmed->get($dateStr)?->first();
                                $requestShift   = $requests->get($dateStr)?->first();
                                $isLocked = in_array($day, $lockedDates);
                            @endphp

                            <td class="shift-day p-2 {{ $isLocked ? 'locked' : '' }}" 
                                data-date="{{ $dateStr }}"
                                style="{{ $isLocked ? 'cursor:not-allowed;' : 'cursor:pointer;' }}">

                                <strong>{{ $day }}</strong>

                                {{-- 【表示優先順位：確定 → 提出中 → 下書き → 締切済】 --}}

                                {{-- 1）確定済 --}}
                                @if($confirmedShift)
                                    @if($confirmedShift->is_day_off)
                                        <div class="text-danger fw-bold small">❌ 確定休</div>
                                    @else
                                        <div class="text-success small fw-semibold">
                                            {{ \Carbon\Carbon::parse($confirmedShift->start_time)->format('H:i') }}〜
                                            {{ \Carbon\Carbon::parse($confirmedShift->end_time)->format('H:i') }}
                                        </div>
                                    @endif
                                @endif

                                {{-- 2）提出中 --}}
                                @if(!$confirmedShift && $requestShift)
                                    @if($requestShift->is_day_off)
                                        <div class="text-primary fw-semibold small">❌ 希望休 (提出中)</div>
                                    @else
                                        <div class="text-primary small fw-semibold">
                                            {{ \Carbon\Carbon::parse($requestShift->start_time)->format('H:i') }}〜
                                            {{ \Carbon\Carbon::parse($requestShift->end_time)->format('H:i') }}
                                            <span class="small">(提出中)</span>
                                        </div>
                                    @endif
                                @endif

                           {{-- 3）下書き --}}
@php
    $draftKey = "shiftDrafts_{$user->id}_{$year}" . str_pad($month, 2, '0', STR_PAD_LEFT);

    $cookieJson = $_COOKIE[$draftKey] ?? null;
    $drafts = [];

    if ($cookieJson && is_string($cookieJson)) {
        $decoded = json_decode($cookieJson, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $drafts = $decoded;
        }
    }

    $draft = $drafts[$dateStr] ?? null;
@endphp

@if(!$confirmedShift && !$requestShift && $draft)
    @if($draft['is_day_off'])
        <div class="text-warning fw-semibold small">❌ 希望休 (下書き)</div>
    @else
        <div class="text-warning small fw-semibold">
            {{ $draft['start_time'] }}〜{{ $draft['end_time'] }} (下書き)
        </div>
    @endif
@endif


                                {{-- 4）締切済（何もデータ無しの場合のみ表示） --}}
                                @if($isLocked && !$confirmedShift && !$requestShift && !$draft)
                                    <div class="text-muted small">⛔ 締切済</div>
                                @endif

                            </td>
                        @endif

                        @php $day++; @endphp
                    @endfor
                </tr>
            @endwhile
            </tbody>
        </table>
    </div>

    {{-- 提出ボタン --}}
    <button id="saveAllBtn" class="btn btn-primary w-100 my-3">
        💾 この月のシフトを提出する
    </button>
</div>

{{-- モーダル --}}
<div class="modal fade" id="shiftModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="shiftForm" class="modal-content">
            <input type="hidden" id="shift_date">

            <div class="modal-header">
                <h5 class="modal-title" id="shiftModalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="form-check form-switch mb-3">
                    <input type="checkbox" id="is_day_off" class="form-check-input">
                    <label class="form-check-label" for="is_day_off">希望休にする</label>
                </div>

                <div id="timeInputs">
                    <input type="text" id="start_time" class="form-control mb-2" placeholder="開始 (例: 09:00 / 25:00)">
                    <input type="text" id="end_time" class="form-control" placeholder="終了 (例: 17:00 / 27:30)">
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-success w-100" type="submit">💾 一時保存</button>
            </div>
        </form>
    </div>
</div>

{{-- ===== CSS ===== --}}
<style>
.locked {
    background-color: #f2f2f2 !important;
    opacity: 0.6;
}
@media (max-width: 576px) {
    h3 { font-size: 1.1rem; }
    h4 { font-size: 1rem; }
    .shift-day { padding: 0.4rem !important; font-size: 0.75rem; min-width: 40px; }
    th { font-size: 0.75rem; padding: 0.4rem; }
    #saveAllBtn { font-size: 0.9rem; padding: 0.7rem; }
    .modal-dialog { max-width: 95%; margin: 0.5rem auto; }
    .modal-body input { font-size: 0.9rem; }
}
</style>

{{-- ===== JS ===== --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const userId        = document.getElementById('shift_user_id').value;
    const year          = document.getElementById('currentYear').value;
    const month         = document.getElementById('currentMonth').value;
    const deadlinePassed = document.getElementById('deadlinePassed').value === '1';
    const lockedDates    = JSON.parse(document.getElementById('lockedDates').value || '[]');

    const draftKey = `shiftDrafts_${userId}_${year}${String(month).padStart(2, '0')}`;
    let shiftData = JSON.parse(localStorage.getItem(draftKey) || '{}');

    // 一時保存（ローカル Draft）
    document.getElementById('shiftForm').addEventListener('submit', e => {
        e.preventDefault();
        const date = document.getElementById('shift_date').value;

        shiftData[date] = {
            is_day_off: document.getElementById('is_day_off').checked,
            start_time: document.getElementById('start_time').value.trim(),
            end_time:   document.getElementById('end_time').value.trim(),
        };

        localStorage.setItem(draftKey, JSON.stringify(shiftData));
        location.reload();
    });

    // セルクリック → ロックでは開けない
    document.querySelectorAll('.shift-day').forEach(cell => {
        cell.addEventListener('click', () => {
            const date = cell.dataset.date;
            const day = parseInt(date.split('-')[2], 10);

            if (lockedDates.includes(day)) return;  // ロック → 何もしない
            if (deadlinePassed) return;

            const s = shiftData[date] ?? { is_day_off:false, start_time:'', end_time:'' };

            document.getElementById('shiftModalTitle').innerText = `シフト入力: ${date}`;
            document.getElementById('shift_date').value = date;
            document.getElementById('is_day_off').checked = s.is_day_off;
            document.getElementById('start_time').value = s.start_time;
            document.getElementById('end_time').value = s.end_time;

            new bootstrap.Modal(document.getElementById('shiftModal')).show();
        });
    });

    // 希望休トグル
    document.getElementById('is_day_off').addEventListener('change', function () {
        document.getElementById('timeInputs').style.opacity = this.checked ? 0.3 : 1;
    });

    // 一括提出
    document.getElementById('saveAllBtn').addEventListener('click', function () {
        if (deadlinePassed) return alert("⛔ 提出期限を過ぎています。");

        fetch("{{ route('shift.user.saveAll', ['user'=>$user->id]) }}", {
            method: "POST",
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                user_id: userId,
                shifts: shiftData
            })
        })
        .then(r => r.json())
        .then(r => {
            if (r.success) {
                localStorage.removeItem(draftKey);
                alert("✅ シフトを提出しました");
                location.reload();
            } else {
                alert(r.message || "⚠️ 提出に失敗しました");
            }
        });
    });
});
</script>
@endsection
