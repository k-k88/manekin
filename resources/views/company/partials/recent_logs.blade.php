<ul class="list-group list-group-flush">
    @foreach($recent_attendances as $log)
        @if($log->clock_in)
            <li class="list-group-item">
                🟢 {{ $log->user->name }} さんが
                {{ \Carbon\Carbon::parse($log->date)->format('Y-m-d') }}
                <span class="fw-bold" style="color: #28a745;">
                    {{ \Carbon\Carbon::parse($log->clock_in)->format('H:i') }}
                </span>
                に出勤しました
            </li>
        @endif
        @if($log->clock_out)
            <li class="list-group-item">
                🔴 {{ $log->user->name }} さんが
                {{ \Carbon\Carbon::parse($log->date)->format('Y-m-d') }}
                <span class="fw-bold" style="color: #dc3545;">
                    {{ \Carbon\Carbon::parse($log->clock_out)->format('H:i') }}
                </span>
                に退勤しました
            </li>
        @endif
    @endforeach
</ul>
 
 