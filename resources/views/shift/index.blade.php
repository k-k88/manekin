@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h3>📅 {{ $user->name }} さんのシフト登録</h3>

    <div id="calendar"></div>

    <button id="saveShifts" class="btn btn-primary mt-3">💾 シフトを保存</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        selectable: true,
        select: function(info) {
            const start = prompt(info.startStr + ' の開始時間 (例: 09:00)');
            const end = prompt(info.startStr + ' の終了時間 (例: 17:00)');
            if (start && end) {
                calendar.addEvent({
                    title: `${start}〜${end}`,
                    start: info.startStr,
                    extendedProps: { start_time: start, end_time: end }
                });
            }
        },
        events: [
            @foreach($shifts as $shift)
            {
                title: "{{ $shift->start_time }}〜{{ $shift->end_time }}",
                start: "{{ $shift->shift_date }}",
                extendedProps: {
                    start_time: "{{ $shift->start_time }}",
                    end_time: "{{ $shift->end_time }}"
                }
            },
            @endforeach
        ]
    });
    calendar.render();

    document.getElementById('saveShifts').addEventListener('click', async () => {
        const events = calendar.getEvents().map(e => ({
            date: e.startStr,
            start_time: e.extendedProps.start_time,
            end_time: e.extendedProps.end_time
        }));
        const response = await fetch("{{ route('shift.store') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ shifts: events })
        });
        if (response.ok) alert('シフトを保存しました！');
    });
});
</script>
@endsection
