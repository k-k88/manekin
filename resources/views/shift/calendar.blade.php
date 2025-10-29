@extends('layouts.shift')

@section('content')
<div class="container">
    <h3 class="mb-3">シフト登録 - {{ $user->name }}</h3>

    @php
        $year = now()->year;
        $month = now()->month;
        $firstDay = \Carbon\Carbon::create($year, $month, 1);
        $lastDay = $firstDay->copy()->endOfMonth();
        $startDayOfWeek = $firstDay->dayOfWeek;
        $day = 1 - $startDayOfWeek;
    @endphp

    <table class="table table-bordered text-center">
        <thead>
            <tr>
                @foreach(['日','月','火','水','木','金','土'] as $dayName)
                    <th>{{ $dayName }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @while($day <= $lastDay->day)
                <tr>
                    @for($i=0; $i<7; $i++)
                        @if($day < 1 || $day > $lastDay->day)
                            <td></td>
                        @else
                            <td class="shift-day" data-date="{{ $year }}-{{ sprintf('%02d',$month) }}-{{ sprintf('%02d',$day) }}">
                                {{ $day }}
                            </td>
                        @endif
                        @php $day++; @endphp
                    @endfor
                </tr>
            @endwhile
        </tbody>
    </table>
</div>

<!-- モーダル -->
<div class="modal fade" id="shiftModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="shiftForm" method="POST" action="{{ route('shift.save') }}" class="modal-content">
            @csrf
            <input type="hidden" name="user_id" value="{{ $user->id }}">
            <input type="hidden" name="shift_date" id="shift_date">
            <div class="modal-header">
                <h5 class="modal-title">シフト登録</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">開始時間</label>
                    <input type="time" class="form-control" name="start_time" id="start_time">
                </div>
                <div class="mb-3">
                    <label class="form-label">終了時間</label>
                    <input type="time" class="form-control" name="end_time" id="end_time">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">保存</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.shift-day').forEach(td => {
        td.addEventListener('click', function() {
            document.getElementById('shift_date').value = this.dataset.date;
            document.getElementById('start_time').value = '';
            document.getElementById('end_time').value = '';
            new bootstrap.Modal(document.getElementById('shiftModal')).show();
        });
    });

    document.getElementById('shiftForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch(this.action, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if(data.success){
                alert('保存しました');
                bootstrap.Modal.getInstance(document.getElementById('shiftModal')).hide();
            } else {
                alert('保存に失敗しました');
            }
        });
    });
});
</script>
@endsection
