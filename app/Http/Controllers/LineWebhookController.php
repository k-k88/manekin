<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\WageHistory;
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
            $userId     = $event['source']['userId'] ?? null;
            $text       = trim($event['message']['text'] ?? '');
            $replyText  = null;

            $user = User::where('line_user_id', $userId)->first();

            // ================================
            // 登録コマンド
            // ================================
            if (preg_match('/^登録\s+([A-Za-z0-9]+)\s+(\d+)$/u', $text, $match)) {
                $replyText = $this->handleRegistration($match[1], $match[2], $userId);
                $user = User::where('line_user_id', $userId)->first(); // 登録後再取得
            }

            // ================================
            // 登録前のガード
            // ================================
            if (!$user && !str_starts_with($text, '登録')) {
                $replyText = "⚠️ 登録がまだです。\n「登録 [企業コード] [社員ID]」を送ってください。";
            }

            // ================================
            // ✅ シフト登録（出勤コマンドより前に判定）
            // ================================
            elseif ($text === 'シフト登録' && $user) {
                $this->replyFlexMessage(
                    $replyToken,
                    '📅 シフト登録',
                    "{$user->name}さん、以下のボタンからシフトを登録できます！",
                    url("/shift/login/{$userId}")
                );
                continue;
            }

            // ================================
            // 出勤 / 休憩 / 退勤
            // ================================
            elseif ($user) {
                $replyText = $this->handleAttendanceCommand($user, $text);
            }

            // ================================
            // 不明コマンド
            // ================================
            if (!$replyText) {
                $replyText = "不明なコマンドです。\n\n🟢利用できるコマンド\n"
                           . "・登録 [企業コード] [社員ID]\n"
                           . "・出勤\n"
                           . "・休憩開始\n"
                           . "・休憩終了\n"
                           . "・退勤\n"
                           . "・シフト登録";
            }

            // ================================
            // LINEへ返信
            // ================================
            if ($replyToken && $replyText) {
                $this->replyTextMessage($replyToken, $replyText);
            }
        }

        return response('OK', 200);
    }

    // =================================================
    // 登録処理
    // =================================================
    protected function handleRegistration(string $companyCode, string $employeeId, string $lineUserId): string
    {
        $company = Company::where('code', $companyCode)->first();
        if (!$company) return "⚠️ 企業コード「{$companyCode}」は存在しません。";

        $user = User::where('id', $employeeId)
            ->where('company_id', $company->id)
            ->first();

        if ($user) {
            $user->line_user_id = $lineUserId;
            $user->save();
            return "✅ {$company->name} の社員ID {$employeeId} を登録しました。";
        }

        return "⚠️ 該当する社員IDが見つかりません。";
    }

    // =================================================
    // 出勤 / 休憩 / 退勤 処理
    // =================================================
    protected function handleAttendanceCommand(User $user, string $command): ?string
    {
        $today = now()->toDateString();
        $attendance = Attendance::firstOrNew(['user_id' => $user->id, 'date' => $today]);
        $attendance->store_id ??= $user->store_id;
        $attendance->company_id ??= $user->company_id;

        switch ($command) {
            case '出勤':
                if ($attendance->clock_in)
                    return "⚠️ 今日はすでに出勤済みです。";
                $attendance->clock_in = now();
                $attendance->save();
                return "🕒 出勤を記録しました。";

            case '休憩開始':
                if (!$attendance->clock_in)
                    return "⚠️ 出勤データがありません。まず「出勤」を送ってください。";
                if ($attendance->break_start)
                    return "⚠️ すでに休憩を開始しています。";
                $attendance->break_start = now();
                $attendance->break_end = null;
                $attendance->save();
                return "☕ 休憩開始を記録しました。\n開始時刻：" . $attendance->break_start->format('H:i');

            case '休憩終了':
                if (!$attendance->break_start)
                    return "⚠️ 休憩開始の記録がありません。";
                if ($attendance->break_end)
                    return "⚠️ すでに休憩終了済みです。";
                $attendance->break_end = now();
                $attendance->break_minutes = $attendance->calculateBreakMinutes();
                $attendance->save();
                return "✅ 休憩終了を記録しました。\n休憩時間：" . $attendance->break_minutes . "分";

            case '退勤':
                if (!$attendance->clock_in)
                    return "⚠️ 出勤していません。";
                if ($attendance->clock_out)
                    return "⚠️ すでに退勤済みです。";

                $attendance->clock_out = now();

                // ✅ 休憩時間を確実に反映
                if ($attendance->break_start && $attendance->break_end) {
                    $attendance->break_minutes = $attendance->calculateBreakMinutes();
                }

                $attendance->save();

                // ✅ 給与テーブルをリアルタイム更新
                \App\Models\Attendance::recalculatePayroll(
                    $attendance->user_id,
                    $attendance->company_id,
                    $attendance->date
                );

                return "🏁 退勤を記録しました。\n本日の給与：¥" . number_format($attendance->pay);

            default:
                return null;
        }
    }

    // =================================================
    // LINE 返信共通
    // =================================================
    protected function replyTextMessage(string $replyToken, string $text)
    {
        $this->api->replyMessage(new ReplyMessageRequest([
            'replyToken' => $replyToken,
            'messages' => [new TextMessage(['type' => 'text', 'text' => $text])],
        ]));
    }

    protected function replyFlexMessage(string $replyToken, string $title, string $desc, string $url)
    {
        $this->api->replyMessage(new ReplyMessageRequest([
            'replyToken' => $replyToken,
            'messages' => [
                new FlexMessage([
                    'type' => 'flex',
                    'altText' => $title,
                    'contents' => [
                        'type' => 'bubble',
                        'body' => [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'contents' => [
                                ['type' => 'text', 'text' => $title, 'weight' => 'bold', 'size' => 'lg'],
                                ['type' => 'text', 'text' => $desc, 'wrap' => true, 'size' => 'sm', 'color' => '#555555'],
                                [
                                    'type' => 'button',
                                    'style' => 'primary',
                                    'color' => '#1DB446',
                                    'action' => [
                                        'type' => 'uri',
                                        'label' => 'シフト登録ページを開く',
                                        'uri' => $url,
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
