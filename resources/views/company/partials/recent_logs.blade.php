<ul class="list-group list-group-flush">
    @foreach($recent_attendances as $log)
        @if($log->clock_in)
            <li class="list-group-item">
                🟢 {{ $log->user->name }} さんが
                {{ \Carbon\Carbon::parse($log->clock_in)->format('H:i') }} に出勤しました
            </li>
        @endif
        @if($log->clock_out)
            <li class="list-group-item">
                🔴 {{ $log->user->name }} さんが
                {{ \Carbon\Carbon::parse($log->clock_out)->format('H:i') }} に退勤しました
            </li>
        @endif
    @endforeach
</ul>
