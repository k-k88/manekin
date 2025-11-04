@extends('layouts.shift')

@section('content')
<div class="container">
    <h3 class="mb-3 text-center">📅 {{ $user->name }} さんのシフト登録</h3>

    @php
        $current = \Carbon\Carbon::create($year, $month, 1);
        $prev = $current->copy()->subMonth();
        $next = $current->copy()->addMonth();
    @endphp

    <div class="d-flex justify-content-between mb-2">
        <a href="{{ route('shift.calendar', ['user'=>$user->id, 'year'=>$prev->year, 'month'=>$prev->month]) }}" class="btn btn-outline-secondary">← 前月</a>
        <h4>{{ $year }}年 {{ $month }}月</h4>
        <a href="{{ route('shift.calendar', ['user'=>$user->id, 'year'=>$next->year, 'month'=>$next->month]) }}" class="btn btn-outline-secondary">次月 →</a>
    </div>

    <table class="table table-bordered text-center">
        <thead>
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
                        @php $shift = $shifts[$dateStr] ?? null; @endphp

                        <td class="shift-day p-2 text-center" data-date="{{ $dateStr }}"
                            @if($shift && $shift->is_day_off)
                                style="background:#dc3545;color:white;font-weight:bold;"
                            @endif>

                            <strong>{{ $day }}</strong>

                            @if($shift)
                                @if($shift->is_day_off)
                                    <div class="fw-bold text-center" style="font-size: 15px; padding-top:4px;">
                                        <span style="font-size:20px;">❌</span> 希望休
                                    </div>
                                @else
                                    <div class="text-success small fw-semibold">
                                        {{ $shift->start_time ? \Carbon\Carbon::parse($shift->start_time)->format('H:i') : '' }}
                                        〜
                                        {{ $shift->end_time ? \Carbon\Carbon::parse($shift->end_time)->format('H:i') : '' }}
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

    <button id="saveAllBtn" class="btn btn-primary w-100 my-3">💾 この月のシフトをまとめて保存</button>
</div>

<!-- モーダル -->
<div class="modal fade" id="shiftModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="shiftForm" method="POST" action="{{ route('shift.save') }}" class="modal-content">
            @csrf
            <input type="hidden" name="shift_date" id="shift_date">

            <div class="modal-header">
                <h5 class="modal-title" id="shiftModalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="form-check form-switch mb-3">
                    <input type="checkbox" id="is_day_off" name="is_day_off" class="form-check-input">
                    <label class="form-check-label">希望休にする</label>
                </div>

                <div id="timeInputs">
                    <label>開始時間（例: 09:00 / 25:00）</label>
                    <input type="text" class="form-control mb-2" id="start_time" name="start_time">

                    <label>終了時間（例: 17:00 / 27:30）</label>
                    <input type="text" class="form-control" id="end_time" name="end_time">
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-success">保存</button>
            </div>
        </form>
    </div>
</div>



<script>
document.addEventListener('DOMContentLoaded', function() {

    const shiftData = {}; // 月全体の下書き
    const stored = @json($shifts);

    document.querySelectorAll('.shift-day').forEach(cell => {
        cell.addEventListener('click', function() {
            const date = this.dataset.date;
            document.getElementById('shiftModalTitle').innerText = `シフト登録: ${date}`;
            document.getElementById('shift_date').value = date;

            // 初期化
            document.getElementById('is_day_off').checked = false;
            document.getElementById('start_time').value = '';
            document.getElementById('end_time').value = '';
            document.getElementById('timeInputs').style.opacity = 1;

            // 既存データの場合
            if (stored[date]) {
                const s = stored[date];
                if (s.is_day_off) {
                    document.getElementById('is_day_off').checked = true;
                    document.getElementById('timeInputs').style.opacity = 0.3;
                } else {
                    const format = t => t ? t.substr(11,5) : '';
                    document.getElementById('start_time').value = format(s.start_time);
                    document.getElementById('end_time').value = format(s.end_time);
                }
            }

            new bootstrap.Modal(document.getElementById('shiftModal')).show();
        });
    });

    document.getElementById('is_day_off').addEventListener('change', function() {
        document.getElementById('timeInputs').style.opacity = this.checked ? 0.3 : 1;
    });

    // 下書き保存
    document.getElementById('shiftForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const date = document.getElementById('shift_date').value;
        const cell = document.querySelector(`td.shift-day[data-date="${date}"]`);

        const isDayOff = document.getElementById('is_day_off').checked;
        const start = document.getElementById('start_time').value;
        const end   = document.getElementById('end_time').value;

        shiftData[date] = { is_day_off: isDayOff ? 1 : 0, start_time: start, end_time: end };

        cell.innerHTML = `<strong>${date.split('-')[2]}</strong>` +
            (isDayOff
                ? `<div style="font-size:18px; font-weight:bold;">❌ 希望休</div>`
                : `<div class="text-success small">${start} 〜 ${end}</div>`
            );

        bootstrap.Modal.getInstance(document.getElementById('shiftModal')).hide();
    });

    // まとめて保存
    document.getElementById('saveAllBtn').addEventListener('click', function() {
        fetch("{{ route('shift.saveAll') }}", {
            method: 'POST',
            headers: {'Content-Type': 'application/json','X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({ shifts: shiftData })
        })
        .then(r => r.json())
        .then(r => {
            if (r.success) {
                alert("✅ シフトをまとめて保存しました");
                location.reload();
            } else {
                alert("⚠️ 保存に失敗しました");
            }
        });
    });

});
</script>
@endsection
