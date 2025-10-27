<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>{{ $company->name }} - {{ $month }} 給与一覧</title>
    <style>
    body {
        font-family: "ipaexg", sans-serif;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        border: 1px solid #333;
        padding: 4px;
        text-align: left;
    }
</style>

</head>
<body>
    <h2>{{ $company->name }} - {{ \Carbon\Carbon::parse($month.'-01')->format('Y年m月') }} 給与一覧</h2>

    <table>
        <thead>
            <tr>
                <th>社員名</th>
                <th>日付</th>
                <th>出勤</th>
                <th>退勤</th>
                <th>勤務時間(h)</th>
                <th>時給(円)</th>
                <th>給与(円)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($attendances as $attendance)
                @php
                    $hours = \Carbon\Carbon::parse($attendance->clock_in)
                             ->diffInMinutes($attendance->clock_out) / 60;
                    $pay = $hours * $hourlyWage;
                @endphp
                <tr>
                    <td>{{ $attendance->user->name }}</td>
                    <td>{{ $attendance->date }}</td>
                    <td>{{ $attendance->clock_in }}</td>
                    <td>{{ $attendance->clock_out }}</td>
                    <td>{{ number_format($hours, 2) }}</td>
                    <td>{{ number_format($hourlyWage) }}</td>
                    <td>{{ number_format($pay) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $totalPay = $attendances->sum(function($a) use ($hourlyWage) {
            return \Carbon\Carbon::parse($a->clock_in)
                   ->diffInMinutes($a->clock_out) / 60 * $hourlyWage;
        });
    @endphp

    <p style="text-align: right; margin-top: 10px; font-weight: bold;">
        総給与: {{ number_format($totalPay) }} 円
    </p>
</body>
</html>
