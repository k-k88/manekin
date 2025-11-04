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
    h2 {
        margin-bottom: 16px;
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
            @php $totalPay = 0; @endphp
 
            @foreach($attendances as $attendance)
                @php
                    $clockIn = \Carbon\Carbon::parse($attendance->date . ' ' . $attendance->clock_in);
                    $clockOut = \Carbon\Carbon::parse($attendance->date . ' ' . $attendance->clock_out);
 
                    // 翌日退勤対応
                    if ($clockOut->lessThanOrEqualTo($clockIn)) {
                        $clockOut->addDay();
                    }
 
                    // 深夜時間帯（22:00〜翌5:00）
                    $nightStart = \Carbon\Carbon::parse($attendance->date . ' 22:00');
                    $nightEnd = \Carbon\Carbon::parse($attendance->date . ' 05:00')->addDay();
 
                    // 深夜労働時間を算出
                    $overlapStart = $clockIn->greaterThan($nightStart) ? $clockIn : $nightStart;
                    $overlapEnd = $clockOut->lessThan($nightEnd) ? $clockOut : $nightEnd;
                    $nightMinutes = $overlapEnd->gt($overlapStart)
                        ? $overlapStart->diffInMinutes($overlapEnd)
                        : 0;
 
                    $totalMinutes = $clockIn->diffInMinutes($clockOut);
                    $normalMinutes = max(0, $totalMinutes - $nightMinutes);
 
                    // 時給（履歴 or 現在時給）
                    $wageHistory = \App\Models\WageHistory::where('user_id', $attendance->user_id)
                        ->where('effective_from', '<=', $attendance->date)
                        ->orderByDesc('effective_from')
                        ->first();
 
                    $hourlyWage = $wageHistory ? $wageHistory->hourly_wage : ($attendance->user->hourly_wage ?? 0);
 
                    // 給与計算（深夜割増1.25倍）
                    $normalPay = ($normalMinutes / 60) * $hourlyWage;
                    $nightPay = ($nightMinutes / 60) * $hourlyWage * 1.25;
                    $pay = round($normalPay + $nightPay);
 
                    $totalPay += $pay;
                    $hours = round($totalMinutes / 60, 2);
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
 
    <p style="text-align: right; margin-top: 10px; font-weight: bold;">
        総給与: {{ number_format($totalPay) }} 円
    </p>
</body>
</html>