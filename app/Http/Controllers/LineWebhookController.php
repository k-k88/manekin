<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Shift;
use Carbon\Carbon;
use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Configuration;
use LINE\Clients\MessagingApi\Model\ReplyMessageRequest;
use LINE\Clients\MessagingApi\Model\TextMessage;
use LINE\Clients\MessagingApi\Model\FlexMessage;

class LineWebhookController extends Controller
{
    protected MessagingApiApi $api;

    public function __construct()
    {
        $config = Configuration::getDefaultConfiguration()
            ->setAccessToken(env('LINE_CHANNEL_ACCESS_TOKEN'));

        $this->api = new MessagingApiApi(null, $config);
    }

    public function webhook(Request $request)
    {
        $events = $request->input('events', []);
        if (empty($events)) return response('No events', 200);

        foreach ($events as $event) {
            $replyToken = $event['replyToken'] ?? null;
            $lineUserId = $event['source']['userId'] ?? null;

            // 全角/半角スペース除去
            $text = preg_replace('/[\s　]+/u', '', $event['message']['text'] ?? '');

            // ★★★ これが重要！switchで使うために追加 ★★★
            $command = $text;
 
            $replyText = null;

            // ■ 登録コマンド（企業コード4桁 + 社員ID 1〜4桁）
            if (preg_match('/^登録([A-Za-z0-9]{4})(\d{1,4})$/u', $text, $m)) {
                $replyText = $this->handleRegistration($m[1], $m[2], $lineUserId);
                $this->maybeReplyText($replyToken, $replyText);
                continue;
            }

            // ■ LINE 未登録ユーザー
            $user = User::where('line_user_id', $lineUserId)->first();
            if (!$user) {
                $replyText = "⚠️ 登録がまだです。\n「登録 [企業コード] [社員ID]」を送ってください。";
                $this->maybeReplyText($replyToken, $replyText);
                continue;
            }

            // ■ シフト登録
            if ($text === 'シフト登録') {
                $this->replyFlexMessage(
                    $replyToken,
                    '📅 シフト登録',
                    "{$user->name}さん、以下からシフトを登録できます！",
                    url("/shift/login/{$lineUserId}")
                );
                continue;
            }

            // ■ 給与計算
            if ($text === '給与計算') {
                [$workedDisplay, $hourly, $totalPay] = $this->calcMonthlySummary($user);
                $replyText = 
                    "💰 今月の給与情報\n\n".
                    "勤務時間：{$workedDisplay}\n".
                    "時給：¥".number_format($hourly)."\n".
                    "合計給与：¥".number_format($totalPay);
                $this->maybeReplyText($replyToken, $replyText);
                continue;
            }

            // ■ 出勤 / 休憩 / 退勤
            $replyText = $this->handleAttendanceCommand($user, $text);

            if (!$replyText) {
                $replyText = "不明なコマンドです。\n\n🟢利用できるコマンド\n"
                            ."・登録 [企業コード] [社員ID]\n"
                            ."・出勤\n"
                            ."・休憩開始\n"
                            ."・休憩終了\n"
                            ."・退勤\n"
                            ."・シフト登録\n"
                            ."・給与計算";
            }

            $this->maybeReplyText($replyToken, $replyText);
        }

        return response('OK', 200);
    }

    // =====================================================
    // 登録処理
    // =====================================================
    protected function handleRegistration(string $companyCode, string $employeeId, string $lineUserId): string
    {
        $company = Company::where('code', $companyCode)->first();
        if (!$company) return "⚠️ 企業コード「{$companyCode}」は存在しません。";

        $user = User::where('id', $employeeId)
            ->where('company_id', $company->id)
            ->first();

        if (!$user) return "⚠️ 該当する社員IDが見つかりません。";

        $user->line_user_id = $lineUserId;
        $user->save();

        return "✅ {$company->name} の社員ID {$employeeId} を登録しました。";
    }

    // =====================================================
    // 出勤 / 休憩 / 退勤 処理（Carbon 完全対応）
    // =====================================================
    protected function handleAttendanceCommand(User $user, string $command): ?string
    {
        $today = now()->toDateString();

        $attendance = Attendance::firstOrNew(['user_id' => $user->id, 'date' => $today]);
        $attendance->store_id   ??= $user->store_id;
        $attendance->company_id ??= $user->company_id;

        // -------------------------
        // シフト取得（Carbon 化）
        // -------------------------
        $shift = Shift::where('user_id', $user->id)
            ->where('shift_date', $today)
            ->first();

        if ($shift) {
            $shiftStart = Carbon::parse("{$today} {$shift->start_time}");
            $shiftEnd   = Carbon::parse("{$today} {$shift->end_time}");
        }

        switch ($command) {

            // ----------------------------------------
            // 出勤
            // ----------------------------------------
            case '出勤':

                if ($attendance->clock_in)
                    return "⚠️ 今日はすでに出勤済みです。";

                if ($shift) {
                    $now = Carbon::now();

                    if ($now->lt($shiftStart)) {
                        return "⚠️ シフト開始前のため出勤できません。\n開始時刻：{$shiftStart->format('H:i')}";
                    }
                }

                $attendance->clock_in = Carbon::now()->format('H:i:s');
                $attendance->save();

                return "🕒 出勤を記録しました。";


            // ----------------------------------------
            // 休憩開始
            // ----------------------------------------
            case '休憩開始':

                if (!$attendance->clock_in)
                    return "⚠️ まず「出勤」を送ってください。";

                if ($attendance->break_start)
                    return "⚠️ すでに休憩を開始しています。";

                $attendance->break_start = Carbon::now()->format('H:i:s');
                $attendance->break_end   = null;
                $attendance->save();

                return "☕ 休憩開始しました。\n開始：" 
                    . Carbon::parse($attendance->break_start)->format('H:i');


            // ----------------------------------------
            // 休憩終了
            // ----------------------------------------
            case '休憩終了':

                if (!$attendance->break_start)
                    return "⚠️ 休憩開始が記録されていません。";

                if ($attendance->break_end)
                    return "⚠️ すでに休憩終了済みです。";

                $attendance->break_end = Carbon::now()->format('H:i:s');
                $attendance->break_minutes = $attendance->calculateBreakMinutes();
                $attendance->save();

                return "✅ 休憩終了しました。\n休憩時間：{$attendance->break_minutes}分";


            // ----------------------------------------
            // 退勤
            // ----------------------------------------
            case '退勤':
                if (!$attendance->clock_in)
                    return "⚠️ 出勤していません。";
                if ($attendance->clock_out)
                    return "⚠️ すでに退勤済みです。";

                if (!$attendance->clock_in)
                    return "⚠️ 出勤していません。";

                if ($attendance->clock_out)
                    return "⚠️ すでに退勤済みです。";

                $attendance->clock_out = Carbon::now()->format('H:i:s');

                if ($attendance->break_start && $attendance->break_end) {
                    $attendance->break_minutes = $attendance->calculateBreakMinutes();
                }

                $attendance->save();

                // 本日の給与
                $todayPay = (int) $attendance->pay;
                $workedMin = $attendance->getTotalWorkMinutes();
                $h = intdiv($workedMin, 60);
                $m = $workedMin % 60;
                $todayWorked = sprintf("%d時間%02d分", $h, $m);

                // 月次サマリ
                [$workedDisplay, $hourly, $totalPay] = $this->calcMonthlySummary($user);

                return "🏁 退勤を記録しました。\n"
                    ."本日の勤務：{$todayWorked}\n"
                    ."本日の給与：¥".number_format($todayPay)."\n\n"
                    ."【今月サマリ】\n"
                    ."勤務時間：{$workedDisplay}\n"
                    ."時給：¥".number_format($hourly)."\n"
                    ."合計給与：¥".number_format($totalPay);

            default:
                return null;
        }
    }

    // =====================================================
    // 月次給与サマリ
    // =====================================================
    protected function calcMonthlySummary(User $user): array
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd   = now()->endOfMonth()->toDateString();

        $attendances = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->whereNotNull('clock_in')
            ->whereNotNull('clock_out')
            ->get();

        $totalMinutes = 0;
        $totalPay = 0;
        $lastHourly = 0;

        foreach ($attendances as $a) {
            $totalMinutes += $a->getTotalWorkMinutes();
            $totalPay     += (int) $a->pay;
            $lastHourly    = $a->effective_wage ?? 0;
        }

        $hours = intdiv($totalMinutes, 60);
        $mins  = $totalMinutes % 60;
        $workedDisplay = sprintf("%d時間%02d分", $hours, $mins);

        return [$workedDisplay, $lastHourly, (int) round($totalPay)];
    }

    // =====================================================
    // LINE 返信共通処理
    // =====================================================
    protected function maybeReplyText(?string $replyToken, ?string $text): void
    {
        if (!$replyToken || !$text) return;

        $this->api->replyMessage(new ReplyMessageRequest([
            'replyToken' => $replyToken,
            'messages'   => [
                new TextMessage([
                    'type' => 'text',
                    'text' => $text
                ])
            ],
        ]));
    }

    protected function replyFlexMessage(string $replyToken, string $title, string $desc, string $url)
    {
        $this->api->replyMessage(new ReplyMessageRequest([
            'replyToken' => $replyToken,
            'messages' => [
                new FlexMessage([
                    'type'    => 'flex',
                    'altText' => $title,
                    'contents' => [
                        'type' => 'bubble',
                        'body' => [
                            'type'   => 'box',
                            'layout' => 'vertical',
                            'contents' => [
                                ['type' => 'text', 'text' => $title, 'weight' => 'bold', 'size' => 'lg'],
                                ['type' => 'text', 'text' => $desc, 'wrap' => true, 'size' => 'sm', 'color' => '#555555'],
                                [
                                    'type' => 'button',
                                    'style' => 'primary',
                                    'color' => '#1DB446',
                                    'action' => [
                                        'type'  => 'uri',
                                        'label' => 'シフト登録ページを開く',
                                        'uri'   => $url,
                                    ]
                                ]
                            ]
                        ]
                    ]
                ])
            ]
        ]));
    }
}
