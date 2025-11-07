@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-3">{{ $company->name }}｜シフト確定カレンダー</h2>

    {{-- 🔄 月変更 --}}
    <form method="GET" class="mb-3">
        <input type="month" name="month" value="{{ $month }}" class="form-control w-auto d-inline">
        <button class="btn btn-primary btn-sm">表示</button>
    </form>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div id="calendar"></div>

    {{-- 💾 保存フォーム --}}
   <form action="{{ route('company.shift.save', $company) }}" method="POST" id="saveForm">

        @csrf
        <div id="shift-inputs"></div>
        <button class="btn btn-success w-100 mt-3">✅ この月のシフトを確定</button>
    </form>
</div>
<div class="mb-3">
        <a href="{{ route('company.dashboard', ['company' => $company->id]) }}" class="btn btn-secondary">
            ← ダッシュボードに戻る
        </a>
    </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.4/index.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let calendarEl = document.getElementById('calendar');
    let shiftRequests = @json($shiftRequests);

    // 🟦 既存提出シフト → Calendarイベントへ
    let events = shiftRequests.map(s => ({
        id: s.id,
        title: s.user.name + (s.is_day_off ? "：休み" : `：${s.start_time ?? ''}〜${s.end_time ?? ''}`),
        start: s.shift_date,
        extendedProps: {
            request_id: s.id,
            user_id: s.user_id,
            user: s.user.name,
            start_time: s.start_time,
            end_time: s.end_time,
            is_day_off: s.is_day_off
        }
    }));

    let calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'ja',
        firstDay: 1,
        height: 650,
        initialDate: "{{ $month }}-01",
        events: events,

        // ✅ 日付クリック → 新規シフト追加
        dateClick(info) {
            let userId = prompt("社員IDを入力してください");
            if (!userId) return;

            let start = prompt("開始時刻 (例: 09:00) 休みなら空欄", "");
            let end = prompt("終了時刻 (例: 18:00) 休みなら空欄", "");
            let is_day_off = (!start && !end) ? 1 : 0;

            let newEvent = calendar.addEvent({
                id: "new_" + Date.now(),
                title: "社員" + userId + (is_day_off ? "：休み" : `：${start}〜${end}`),
                start: info.dateStr,
                extendedProps: {
                    request_id: null,
                    user_id: userId,
                    start_time: start,
                    end_time: end,
                    is_day_off: is_day_off
                }
            });

            addHiddenInputs(newEvent);
        },

        // ✅ イベントクリック → シフト修正
        eventClick(info) {
            let ev = info.event;
            let start = prompt("開始時刻 (休みなら空欄)", ev.extendedProps.start_time ?? "");
            let end = prompt("終了時刻 (休みなら空欄)", ev.extendedProps.end_time ?? "");
            let is_day_off = (!start && !end) ? 1 : 0;

            ev.setProp('title', ev.extendedProps.user + (is_day_off ? "：休み" : `：${start}〜${end}`));
            ev.setExtendedProp('start_time', start);
            ev.setExtendedProp('end_time', end);
            ev.setExtendedProp('is_day_off', is_day_off);

            addHiddenInputs(ev);
        }
    });

    calendar.render();

    // ✅ hidden input を1イベントにつき1セットで管理
    function addHiddenInputs(ev) {
        let id = ev.extendedProps.request_id ?? ev.id;
        let wrapId = `shift-wrap-${id}`;
        document.getElementById(wrapId)?.remove();

        let html = `
            <div id="${wrapId}">
                <input type="hidden" name="shifts[${id}][user_id]" value="${ev.extendedProps.user_id}">
                <input type="hidden" name="shifts[${id}][start_time]" value="${ev.extendedProps.start_time}">
                <input type="hidden" name="shifts[${id}][end_time]" value="${ev.extendedProps.end_time}">
                <input type="hidden" name="shifts[${id}][is_day_off]" value="${ev.extendedProps.is_day_off}">
                <input type="hidden" name="shifts[${id}][date]" value="${ev.startStr}">
            </div>
        `;
        document.querySelector('#shift-inputs').insertAdjacentHTML("beforeend", html);
    }
});
</script>
@endsection
