@extends('layouts.app')

@section('content')
<div class="container">
    <h2>シフトカレンダー（管理）</h2>

    <div class="d-flex align-items-center mb-3">
        <form method="GET" class="me-3">
            <input type="month" name="month" value="{{ $month }}" onchange="this.form.submit()" class="form-control">
        </form>
        <a href="{{ route('company.dashboard', ['company' => $company->id]) }}" class="btn btn-secondary">
            ← ダッシュボードに戻る
        </a>
    </div>

    @php $current = $start->copy(); @endphp

    <table class="table table-bordered mt-3 calendar-table text-center">
        <thead>
            <tr>
                @foreach(['日','月','火','水','木','金','土'] as $day)
                    <th>{{ $day }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
        @while($current <= $end)
            <tr>
            @for($i = 0; $i < 7; $i++)
                @php $dateKey = $current->format('Y-m-d'); @endphp
                <td class="align-top calendar-cell"
                    data-date="{{ $dateKey }}"
                    onclick="openShiftModal('{{ $dateKey }}')"
                    style="@if($current->month != $mon) background:#eee; @endif">

                    <div><strong>{{ $current->day }}</strong></div>

                    {{-- 登録済みシフト --}}
                    @if(isset($confirmed[$dateKey]))
                        @foreach($confirmed[$dateKey] as $shift)
                            @php
                                if($shift->is_paid_leave){
                                    $displayTime = '有休';
                                    $bgColor = '#cfe2ff';
                                } elseif($shift->is_day_off){
                                    $displayTime = '希望休';
                                    $bgColor = '#fff3cd';
                                } else {
                                    $displayTime = $shift->start_time && $shift->end_time
                                        ? \Carbon\Carbon::parse($shift->start_time)->format('H:i')
                                          .'〜'.\Carbon\Carbon::parse($shift->end_time)->format('H:i')
                                        : '';
                                    $bgColor = '#d1e7dd';
                                }
                            @endphp
                            <div class="alert p-1 m-1 small shift-item"
                                 style="background-color: {{ $bgColor }};"
                                 onclick="event.stopPropagation(); editShift({{ $shift->id }})">
                                {{ $shift->user->name }}<br>{{ $displayTime }}
                            </div>
                        @endforeach
                    @endif
                </td>
                @php $current->addDay(); @endphp
            @endfor
            </tr>
        @endwhile
        </tbody>
    </table>
</div>

<!-- モーダル -->
<div class="modal fade" id="shiftModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">シフト登録・編集</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="shiftForm">
            <input type="hidden" name="date" id="shift_date">
            <input type="hidden" name="shift_id" id="edit_shift_id">

            {{-- ★★ これが超重要（希望休が保存されるようになる） --}}
            <input type="hidden" name="is_day_off" id="is_day_off" value="0">
            <input type="hidden" name="is_paid_leave" id="is_paid_leave" value="0">

            <div id="existing_shifts" class="mb-3 small"></div>

            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label>従業員</label>
                    <select name="user_id" id="shift_user" class="form-select">
                        <option value="">選択してください</option>
                        @foreach($company->users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label>種類</label><br>
                    <select name="shift_type" id="shift_type" class="form-select">
                        <option value="0">出勤</option>
                        <option value="1">希望休</option>
                        <option value="2">有休</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>開始時間</label>
                    <input type="time" name="start_time" id="shift_start" class="form-control">
                </div>
                <div class="col-md-3">
                    <label>終了時間</label>
                    <input type="time" name="end_time" id="shift_end" class="form-control">
                </div>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger me-auto" id="deleteBtn" onclick="deleteShift()" style="display:none;">🗑 削除</button>
        <button type="button" class="btn btn-primary" onclick="saveShift()">💾 保存</button>
      </div>
    </div>
  </div>
</div>

<style>
.calendar-table { table-layout: fixed; width: 100%; }
.calendar-cell { height: 110px; cursor: pointer; vertical-align: top; }
.calendar-cell:hover { background-color: #f2f9ff; }
.small { font-size: 0.75rem; }
.shift-item:hover { opacity: 0.85; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const shiftModal = new bootstrap.Modal('#shiftModal');

    function extractTime(value){
        if(!value) return '';
        return value.length >= 16 ? value.slice(11,16) : value.slice(0,5);
    }

    // ★ shift_type → is_day_off と is_paid_leave 自動連動
    document.getElementById('shift_type').addEventListener('change', function(){
        const type = Number(this.value);

        if (type === 1) { 
            document.getElementById('is_day_off').value = 1;
            document.getElementById('is_paid_leave').value = 0;
        }
        else if (type === 2) { 
            document.getElementById('is_day_off').value = 0;
            document.getElementById('is_paid_leave').value = 1;
        }
        else {
            document.getElementById('is_day_off').value = 0;
            document.getElementById('is_paid_leave').value = 0;
        }
    });

    window.openShiftModal = function(date) {
        document.getElementById('modalTitle').textContent = "新規シフト登録";
        document.getElementById('shift_date').value = date;
        document.getElementById('edit_shift_id').value = '';
        document.getElementById('deleteBtn').style.display = 'none';

        document.getElementById('shift_user').value = '';
        document.getElementById('shift_start').value = '';
        document.getElementById('shift_end').value = '';
        document.getElementById('shift_type').value = 0;
        document.getElementById('is_day_off').value = 0;
        document.getElementById('is_paid_leave').value = 0;

        fetch(`/company/{{ $company->id }}/shift/requests/${date}`)
            .then(r => r.json())
            .then(data => {
                let html = '';
                if(data.length === 0){
                    html = '<p class="text-muted">この日の登録・希望シフトはありません。</p>';
                } else {
                    data.forEach(s => {
                        let start = s.is_day_off === 0 ? extractTime(s.start_time) : '';
                        let end   = s.is_day_off === 0 ? extractTime(s.end_time) : '';

                        const kind = s.status === 'confirmed' ? '✅確定' : '📝希望';
                        const typeName = s.is_day_off === 0
                            ? ''
                            : (s.is_paid_leave ? '(有休)' : '(希望休)');

                        html += `<div style="cursor:pointer;"
                                 onclick="applyRequestShift(${s.user_id}, '${start}', '${end}', ${s.is_day_off}, ${s.is_paid_leave})">
                                    ${kind}：${s.user_name} ${typeName} ${s.is_day_off === 0 ? start+'〜'+end : ''}
                                 </div>`;
                    });
                }
                document.getElementById('existing_shifts').innerHTML = html;
                shiftModal.show();
            });
    };

    // ★ 希望休・有休・出勤を正しく反映させる
    window.applyRequestShift = function(userId, start, end, isDayOff, isPaidLeave){
        document.getElementById('shift_user').value = userId;
        document.getElementById('shift_start').value = start;
        document.getElementById('shift_end').value   = end;

        if (isPaidLeave) {
            document.getElementById('shift_type').value = 2;
            document.getElementById('is_day_off').value = 0;
            document.getElementById('is_paid_leave').value = 1;
        }
        else if (isDayOff) {
            document.getElementById('shift_type').value = 1;
            document.getElementById('is_day_off').value = 1;
            document.getElementById('is_paid_leave').value = 0;
        }
        else {
            document.getElementById('shift_type').value = 0;
            document.getElementById('is_day_off').value = 0;
            document.getElementById('is_paid_leave').value = 0;
        }
    };

    window.editShift = function(id){
        fetch(`/company/{{ $company->id }}/shift/${id}`)
            .then(r => r.json())
            .then(shift => {
                document.getElementById('modalTitle').textContent = "シフト編集";
                document.getElementById('edit_shift_id').value = shift.id;
                document.getElementById('shift_date').value = shift.shift_date.slice(0,10);

                document.getElementById('shift_user').value = shift.user_id;
                document.getElementById('shift_start').value = extractTime(shift.start_time);
                document.getElementById('shift_end').value   = extractTime(shift.end_time);

                if (shift.is_paid_leave) {
                    document.getElementById('shift_type').value = 2;
                    document.getElementById('is_day_off').value = 0;
                    document.getElementById('is_paid_leave').value = 1;
                }
                else if (shift.is_day_off) {
                    document.getElementById('shift_type').value = 1;
                    document.getElementById('is_day_off').value = 1;
                    document.getElementById('is_paid_leave').value = 0;
                }
                else {
                    document.getElementById('shift_type').value = 0;
                    document.getElementById('is_day_off').value = 0;
                    document.getElementById('is_paid_leave').value = 0;
                }

                document.getElementById('deleteBtn').style.display = 'inline-block';
                document.getElementById('existing_shifts').innerHTML = '';
                shiftModal.show();
            });
    };

    window.saveShift = function() {
        const formData = new FormData(document.getElementById('shiftForm'));
        fetch('{{ route("company.shift.save", $company->id) }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: formData
        })
        .then(r => r.json())
        .then(() => { shiftModal.hide(); location.reload(); })
        .catch(() => alert('保存に失敗しました'));
    };

    window.deleteShift = function(){
        const id = document.getElementById('edit_shift_id').value;
        if(!confirm('このシフトを削除しますか？')) return;

        fetch(`/company/{{ $company->id }}/shift/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        })
        .then(r => r.json())
        .then(res => { if(res.success){ shiftModal.hide(); location.reload(); } });
    };
});
</script>
@endsection
