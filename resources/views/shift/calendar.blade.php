@extends('layouts.shift')

@section('content')
<div class="container">
    <h3 class="mb-3 text-center">📅 {{ $user->name }} さんのシフト登録</h3>

    <input type="hidden" id="shift_user_id" value="{{ $user->id }}">

    @php
        $current = \Carbon\Carbon::create($year, $month, 1);
        $prev = $current->copy()->subMonth();
        $next = $current->copy()->addMonth();
    @endphp

    <div class="d-flex justify-content-between mb-2">
        <a href="{{ route('shift.user.calendar', ['user'=>$user->id, 'year'=>$prev->year, 'month'=>$prev->month]) }}" class="btn btn-outline-secondary">← 前月</a>
        <h4>{{ $year }}年 {{ $month }}月</h4>
        <a href="{{ route('shift.user.calendar', ['user'=>$user->id, 'year'=>$next->year, 'month'=>$next->month]) }}" class="btn btn-outline-secondary">次月 →</a>
    </div>

    <table class="table table-bordered text-center align-middle">
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

                        {{-- ✅ 修正：ここで first() を使う --}}
                        @php
                            $confirmedShift = isset($confirmed[$dateStr]) ? $confirmed[$dateStr]->first() : null;
                            $requestShift   = isset($requests[$dateStr]) ? $requests[$dateStr]->first() : null;
                        @endphp

                        <td class="shift-day p-2" data-date="{{ $dateStr }}">
                            <strong>{{ $day }}</strong>

                            {{-- 🟦 提出中 --}}
                            @if($requestShift)
                                @if($requestShift->is_day_off)
                                    <div class="text-primary fw-semibold small">❌ 希望休 (提出中)</div>
                                @else
                                    <div class="text-primary small fw-semibold">
                                        {{ \Carbon\Carbon::parse($requestShift->start_time)->format('H:i') }}〜
                                        {{ \Carbon\Carbon::parse($requestShift->end_time)->format('H:i') }}
                                        <span class="small">(提出中)</span>
                                    </div>
                                @endif

                            {{-- 🟩 確定 --}}
                            @elseif($confirmedShift)
                                @if($confirmedShift->is_day_off)
                                    <div class="text-danger fw-bold small">❌ 確定休</div>
                                @else
                                    <div class="text-success small fw-semibold">
                                        {{ \Carbon\Carbon::parse($confirmedShift->start_time)->format('H:i') }}〜
                                        {{ \Carbon\Carbon::parse($confirmedShift->end_time)->format('H:i') }}
                                    </div>
                                @endif
                            @endif

                        </td>
                    @endif

                    @php $day++; @endphp
                @endfor
            </tr>
        @endwhile
        </tbody>
    </table>

    <button id="saveAllBtn" class="btn btn-primary w-100 my-3">💾 この月のシフトを提出する</button>
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
                    <label class="form-check-label">希望休にする</label>
                </div>

                <div id="timeInputs">
                    <input type="text" id="start_time" class="form-control mb-2" placeholder="開始 (例: 09:00 / 25:00)">
                    <input type="text" id="end_time" class="form-control" placeholder="終了 (例: 17:00 / 27:30)">
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-success" type="submit">一時保存</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    let shiftData = JSON.parse(localStorage.getItem('shiftDrafts') || '{}');

    // 下書き反映
    for (const date in shiftData) {
        const cell = document.querySelector(`td[data-date="${date}"]`);
        if (!cell) continue;
        const s = shiftData[date];
        cell.innerHTML += s.is_day_off
            ? `<div class="text-warning fw-semibold small">❌ 希望休 (下書き)</div>`
            : `<div class="text-warning small fw-semibold">${s.start_time}〜${s.end_time} (下書き)</div>`;
    }

    // モーダルを開く
    document.querySelectorAll('.shift-day').forEach(cell => {
        cell.addEventListener('click', () => {
            const date = cell.dataset.date;
            document.getElementById('shiftModalTitle').innerText = `シフト入力: ${date}`;
            document.getElementById('shift_date').value = date;

            const s = shiftData[date] ?? { is_day_off:false, start_time:'', end_time:'' };
            document.getElementById('is_day_off').checked = s.is_day_off;
            document.getElementById('start_time').value = s.start_time;
            document.getElementById('end_time').value = s.end_time;

            document.getElementById('timeInputs').style.opacity = s.is_day_off ? 0.3 : 1;

            new bootstrap.Modal(document.getElementById('shiftModal')).show();
        });
    });

    // 一時保存
    document.getElementById('shiftForm').addEventListener('submit', e => {
        e.preventDefault();
        const date = document.getElementById('shift_date').value;
        shiftData[date] = {
            is_day_off: document.getElementById('is_day_off').checked,
            start_time: document.getElementById('start_time').value,
            end_time: document.getElementById('end_time').value
        };
        localStorage.setItem('shiftDrafts', JSON.stringify(shiftData));
        location.reload();
    });

    // 月提出
    document.getElementById('saveAllBtn').addEventListener('click', function () {
        fetch("{{ route('shift.user.saveAll', ['user'=>$user->id]) }}", {
            method: "POST",
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                user_id: document.getElementById('shift_user_id').value,
                shifts: shiftData
            })
        })
        .then(r => r.json())
        .then(r => {
            if (r.success) {
                localStorage.removeItem('shiftDrafts');
                alert("✅ シフトを提出しました");
                location.reload();
            }
        });
    });

});
</script>
@endsection
